<?php
declare(strict_types=1);
namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261031130000 extends AbstractMigration
{
    public function getDescription(): string { return 'v0.3: nullable stable import identities; preserve all legacy records'; }

    public function up(Schema $schema): void
    {
        $this->abortIf(!$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform, 'This migration requires PostgreSQL.');
        foreach (['learning_path' => '4D04C797', 'course' => '169E6FB9', 'sprint' => 'EF8055B7', 'card' => '161498D3', 'learning_task' => '142E97BD'] as $table => $hash) {
            $this->addSql('ALTER TABLE '.$table.' ADD import_key VARCHAR(201) DEFAULT NULL');
            $this->addSql('CREATE UNIQUE INDEX UNIQ_'.$hash.'1EFD0B29 ON '.$table.' (import_key)');
        }
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException('Удаление внешних идентификаторов нарушает повторный импорт. Автоматический rollback отключён.');
    }
}
