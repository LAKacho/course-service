<?php
namespace App\Tests;
use App\Assessment\Entity\{Assessment, AssessmentAttempt, AssessmentResult, Question, AnswerOption, Skill, SkillAssessment, UserAnswer};
use App\Assessment\Service\{BankInstaller, AssessmentService, AssessmentView, ScoringService};
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpKernel\Exception\{ConflictHttpException, NotFoundHttpException};

class EntryAssessmentTest extends WebTestCase
{
    private function prepare(): \Symfony\Bundle\FrameworkBundle\KernelBrowser
    {
        $client = self::createClient(); $em = $this->em(); $params = $em->getConnection()->getParams();
        self::assertSame('pdo_sqlite', $params['driver']); self::assertStringEndsWith('var/test.db', str_replace('\\', '/', $params['path']));
        $schema = new SchemaTool($em); $metadata = $em->getMetadataFactory()->getAllMetadata();
        $schema->dropSchema($metadata); $schema->createSchema($metadata);
        \App\Tests\Support\LegacyAccessFixture::install($em);
        self::getContainer()->get(BankInstaller::class)->install();
        \App\Tests\Support\DemoSession::attach($client); return $client;
    }
    private function em() { return self::getContainer()->get('doctrine')->getManager(); }
    private function service(): AssessmentService { return self::getContainer()->get(AssessmentService::class); }
    private function start(string $user = 'demo-student', string $code = 'go_backend'): AssessmentAttempt
    {
        return $this->service()->start($this->em()->getRepository(Assessment::class)->findOneBy(['code' => $code]), $user);
    }
    private function complete(AssessmentAttempt $attempt, bool $correct = true): void
    {
        foreach ($attempt->snapshot['questions'] as $question) {
            $selected = $correct ? $question['correctAnswer'] : [array_values(array_diff(array_column($question['options'], 'code'), $question['correctAnswer']))[0]];
            $this->service()->answer($attempt, $attempt->userId, $question['key'], $selected);
        }
        $this->service()->finish($attempt, $attempt->userId);
    }
    public function testBankQualityAndIdempotentInstall(): void
    {
        $this->prepare();
        self::assertSame(48, $this->em()->getRepository(Question::class)->count([])); self::assertSame(17, $this->em()->getRepository(Skill::class)->count([]));
        self::assertSame(['skills' => 0, 'questions' => 0, 'assessments' => 0], self::getContainer()->get(BankInstaller::class)->install());
        $catalog = self::getContainer()->get(AssessmentView::class)->catalog();
        self::assertSame([40, 35, 48], array_column($catalog, 'questionCount'));
        self::assertSame(4, count(array_unique(array_map(static fn($q) => $q->type, $this->em()->getRepository(Question::class)->findAll()))));
    }
    public function testAllCorrectStillDoesNotConfirmSenior(): void
    {
        $this->prepare(); $attempt = $this->start(code: 'full_backend'); $this->complete($attempt);
        $view = self::getContainer()->get(AssessmentView::class)->result($attempt);
        self::assertSame(100.0, $view['percentCorrect']); self::assertSame('middle_candidate', $view['level']);
        self::assertSame('Умеренная', $view['confidence']); self::assertCount(17, $view['skills']);
        self::assertSame(9, $view['difficultyBreakdown']['senior']['correct']);
        self::assertStringContainsString('не подтверждение', $view['notice']);
    }
    public function testSimpleQuestionsAndPartialMultipleChoiceDoNotInflateLevel(): void
    {
        $skill = ['id' => 1, 'code' => 'php', 'title' => 'PHP', 'recommendation' => 'Повторить']; $questions = []; $answers = [];
        for ($i = 0; $i < 12; ++$i) { $questions[] = ['key' => 'q'.$i, 'difficulty' => 'beginner', 'weight' => 1, 'correctAnswer' => ['a'], 'skills' => [$skill]]; $answers['q'.$i] = ['a']; }
        $scoring = new ScoringService();
        $result = $scoring->score(['questions' => $questions, 'coreSkills' => ['php']], $answers);
        self::assertSame(100.0, $result['percentCorrect']); self::assertSame('beginner', $result['preliminaryLevel']); self::assertSame('low', $result['confidence']);
        $questions[] = ['key' => 'multi', 'difficulty' => 'senior', 'weight' => 4, 'correctAnswer' => ['a', 'b'], 'skills' => [$skill]];
        $answers['multi'] = ['a']; $result = $scoring->score(['questions' => $questions, 'coreSkills' => ['php']], $answers);
        self::assertSame(12, $result['correctCount']); self::assertSame(75.0, $result['weightedPercent']);
        self::assertSame(0, $result['difficultyBreakdown']['senior']['correct']);
    }
    public function testSnapshotAndHistoricalResultSurviveBankChanges(): void
    {
        $this->prepare(); $attempt = $this->start(); $snapshot = $attempt->snapshot;
        $question = $this->em()->find(Question::class, $snapshot['questions'][0]['id']);
        $question->text = 'Новый текст банка'; $question->correctAnswer = ['d']; ++$question->version;
        $option = $this->em()->getRepository(AnswerOption::class)->findOneBy(['question' => $question]); $option->text = 'Изменённый вариант';
        $skill = $this->em()->find(Skill::class, $snapshot['questions'][0]['skills'][0]['id']); $skill->title = 'Новая компетенция';
        $attempt->assessment->title = 'Новое название банка'; $this->em()->flush();
        self::assertSame($snapshot, $attempt->snapshot);
        $this->complete($attempt);
        $result = self::getContainer()->get(AssessmentView::class)->result($attempt);
        self::assertSame(100.0, $result['percentCorrect']); self::assertSame('Go Backend', $result['title']);
        self::assertStringNotContainsString('Новая компетенция', json_encode($result, JSON_UNESCAPED_UNICODE));
        $this->service()->finish($attempt, 'demo-student'); self::assertSame(1, $this->em()->getRepository(AssessmentResult::class)->count([]));
        self::assertSame($result, self::getContainer()->get(AssessmentView::class)->result($attempt));
        $next = $this->start(); self::assertNotSame($attempt->publicId, $next->publicId);
        self::assertSame('Новый текст банка', $next->snapshot['questions'][0]['text']); self::assertSame(2, $next->snapshot['questions'][0]['version']);
    }
    public function testSeveralStudentsHaveIndependentAttemptsAndOwnership(): void
    {
        $client = $this->prepare(); $first = $this->start(); $other = $this->start('other-student');
        self::assertNotSame($first->publicId, $other->publicId);
        self::assertSame($first->publicId, $this->start()->publicId);
        foreach (['/api/me/assessment-attempts/'.$other->publicId, '/assessments/attempts/'.$other->publicId, '/api/me/assessment-attempts/'.$other->publicId.'/result'] as $url) {
            $client->request('GET', $url.'?userId=other-student'); self::assertResponseStatusCodeSame(404);
        }
        self::assertSame(0, $this->em()->getRepository(UserAnswer::class)->count([]));
    }
    public function testAnswerOwnershipIsCheckedAtServiceBoundary(): void
    {
        $this->prepare(); $attempt = $this->start('other-student'); $question = $attempt->snapshot['questions'][0];
        $this->expectException(NotFoundHttpException::class);
        $this->service()->answer($attempt, 'demo-student', $question['key'], $question['correctAnswer']);
    }
    public function testRepeatAnswerIsIdempotentAndCannotBeChanged(): void
    {
        $this->prepare(); $attempt = $this->start(); $question = $attempt->snapshot['questions'][0];
        $this->service()->answer($attempt, 'demo-student', $question['key'], $question['correctAnswer']);
        $this->service()->answer($attempt, 'demo-student', $question['key'], $question['correctAnswer']);
        self::assertSame(1, $this->em()->getRepository(UserAnswer::class)->count([]));
        $wrong = array_values(array_diff(array_column($question['options'], 'code'), $question['correctAnswer']))[0];
        try { $this->service()->answer($attempt, 'demo-student', $question['key'], [$wrong]); self::fail('Answer change must fail'); }
        catch (ConflictHttpException) { self::assertTrue(true); }
        self::assertSame(1, (int) $this->em()->getConnection()->fetchOne('SELECT count(*) FROM entry_user_answer'));
    }
    public function testUnfinishedAttemptHasNoResultAndCanResume(): void
    {
        $client = $this->prepare(); $attempt = $this->start(); $id = $attempt->publicId;
        $client->request('GET', '/api/me/assessment-attempts/'.$id.'/result'); self::assertResponseStatusCodeSame(409);
        $client->request('GET', '/assessments/attempts/'.$id.'/result'); self::assertResponseStatusCodeSame(409);
        $attempt = $this->service()->owned($id, 'demo-student');
        try { $this->service()->finish($attempt, 'demo-student'); self::fail('Incomplete attempt cannot finish'); }
        catch (ConflictHttpException) { self::assertTrue(true); }
        self::assertSame(0, (int) $this->em()->getConnection()->fetchOne('SELECT count(*) FROM entry_result'));
        $client->request('GET', '/assessments/attempts/'.$id); self::assertResponseIsSuccessful();
        self::assertSame('in_progress', $this->service()->owned($id, 'demo-student')->status);
    }
    public function testOnlyCurrentQuestionAndNoScoringKeysReachStudent(): void
    {
        $client = $this->prepare(); $attempt = $this->start(); $id = $attempt->publicId;
        $future = $attempt->snapshot['questions'][3];
        $client->request('GET', '/api/me/assessment-attempts/'.$id); self::assertResponseIsSuccessful();
        $json = json_decode($client->getResponse()->getContent(), true);
        self::assertSame(['key', 'type', 'text', 'codeSnippet', 'language', 'options'], array_keys($json['currentQuestion']));
        foreach (['correctAnswer', 'explanation', 'sourceHash', 'difficulty', 'weight', $future['text']] as $secret) { self::assertStringNotContainsString($secret, $client->getResponse()->getContent()); }
        $client->request('GET', '/assessments/attempts/'.$id); self::assertResponseIsSuccessful();
        self::assertStringNotContainsString($future['text'], $client->getResponse()->getContent()); self::assertStringNotContainsString('correctAnswer', $client->getResponse()->getContent());
        $client->request('POST', '/assessments/attempts/'.$id.'/answer/'.$future['key'], ['answer' => ['selection' => 'a']]);
        self::assertResponseStatusCodeSame(404); self::assertStringNotContainsString($future['text'], $client->getResponse()->getContent());
    }
    public function testCsrfAndInvalidSelectionNeverSave(): void
    {
        $client = $this->prepare();
        $client->request('POST', '/assessments/go_backend/start'); self::assertResponseStatusCodeSame(403);
        $attempt = $this->start(); $id = $attempt->publicId; $key = $attempt->snapshot['questions'][0]['key'];
        $client->request('POST', '/assessments/attempts/'.$id.'/answer/'.$key, ['answer' => ['selection' => 'a']]); self::assertResponseStatusCodeSame(422);
        self::assertSame(0, $this->em()->getRepository(UserAnswer::class)->count([]));
        $client->request('POST', '/assessments/attempts/'.$id.'/finish'); self::assertResponseStatusCodeSame(403);
        $crawler = $client->request('GET', '/assessments/attempts/'.$id);
        $form = $crawler->selectButton('Сохранить ответ и продолжить →')->form();
        $values = $form->getPhpValues(); $values['answer']['selection'] = 'not-an-option';
        $client->request('POST', '/assessments/attempts/'.$id.'/answer/'.$key, $values); self::assertResponseStatusCodeSame(422);
        self::assertSame(0, $this->em()->getRepository(UserAnswer::class)->count([]));
    }
    public function testWrongAnswersProduceRecommendationsWithoutLearningChanges(): void
    {
        $this->prepare(); $attempt = $this->start(); $this->complete($attempt, false);
        $result = self::getContainer()->get(AssessmentView::class)->result($attempt);
        self::assertSame(0.0, $result['percentCorrect']); self::assertSame('beginner', $result['level']);
        self::assertNotEmpty($result['recommendations']); self::assertSame([], $result['strengths']);
        foreach (['task_progress', 'enrollment', 'quest_activity', 'quest_prerequisite'] as $table) {
            self::assertSame(0, (int) $this->em()->getConnection()->fetchOne('SELECT count(*) FROM '.$table));
        }
    }
    public function testCodeIsEscapedAndNotExecuted(): void
    {
        $client = $this->prepare(); $assessment = $this->em()->getRepository(Assessment::class)->findOneBy(['code' => 'go_backend']);
        $question = $assessment->questions->first(); $question->type = 'code_analysis'; $question->codeSnippet = '<script>window.USER_CODE_EXECUTED=true</script>'; $this->em()->flush();
        $attempt = $this->start(); $client->request('GET', '/assessments/attempts/'.$attempt->publicId); self::assertResponseIsSuccessful();
        self::assertStringContainsString('&lt;script&gt;', $client->getResponse()->getContent());
        self::assertStringNotContainsString('<script>window.USER_CODE_EXECUTED', $client->getResponse()->getContent());
    }

    public function testResultWriteFailureRollsBackWithoutLosingSavedAnswers(): void
    {
        $this->prepare(); $attempt = $this->start();
        foreach ($attempt->snapshot['questions'] as $question) { $this->service()->answer($attempt, 'demo-student', $question['key'], $question['correctAnswer']); }
        $db = $this->em()->getConnection();
        $db->executeStatement("CREATE TRIGGER fail_entry_result BEFORE INSERT ON entry_skill_assessment WHEN NEW.skill_code = 'sql' BEGIN SELECT RAISE(ABORT, 'assessment result probe'); END");
        try { $this->service()->finish($attempt, 'demo-student'); self::fail('Result write must fail'); }
        catch (\Doctrine\DBAL\Exception $exception) { self::assertStringContainsString('assessment result probe', $exception->getMessage()); }
        self::assertSame(0, (int) $db->fetchOne('SELECT count(*) FROM entry_result'));
        self::assertSame(0, (int) $db->fetchOne('SELECT count(*) FROM entry_skill_assessment'));
        self::assertSame(35, (int) $db->fetchOne('SELECT count(*) FROM entry_user_answer'));
        self::assertSame('in_progress', $db->fetchOne('SELECT status FROM entry_attempt'));
        self::assertNotNull($db->fetchOne('SELECT active_key FROM entry_attempt'));
    }

    public function testUnknownMethodVersionCannotSilentlyRescoreAttempt(): void
    {
        $this->expectException(\DomainException::class);
        (new ScoringService())->score(['methodVersion' => 'future-unknown'], []);
    }
}
