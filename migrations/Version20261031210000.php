<?php
declare(strict_types=1);
namespace DoctrineMigrations;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
final class Version20261031210000 extends AbstractMigration
{
    public function getDescription():string{return 'Add explicit name-only demo accounts; existing learning data is preserved';}
    public function up(Schema $schema):void
    {
        $this->abortIf(!$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform,'PostgreSQL required.');
        $this->addSql('CREATE TABLE demo_account (id VARCHAR(100) NOT NULL, display_name VARCHAR(180) NOT NULL, enabled BOOLEAN NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
    }
    public function down(Schema $schema):void{$this->throwIrreversibleMigrationException('Account deletion is not automatic.');}
}
