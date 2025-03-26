<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250326171552 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE SEQUENCE role_id_seq INCREMENT BY 1 MINVALUE 1 START 1
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE role (id INT NOT NULL, name VARCHAR(255) NOT NULL, PRIMARY KEY(id))
        SQL);
        $this->addSql(<<<'SQL'
            CREATE UNIQUE INDEX UNIQ_57698A6A5E237E06 ON role (name)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE user_roles (user_id INT NOT NULL, role_id INT NOT NULL, PRIMARY KEY(user_id, role_id))
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_54FCD59FA76ED395 ON user_roles (user_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_54FCD59FD60322AC ON user_roles (role_id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE user_roles ADD CONSTRAINT FK_54FCD59FA76ED395 FOREIGN KEY (user_id) REFERENCES "user" (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE user_roles ADD CONSTRAINT FK_54FCD59FD60322AC FOREIGN KEY (role_id) REFERENCES role (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE "user" DROP roles
        SQL);
        $this->addSql(<<<'SQL'
            CREATE UNIQUE INDEX UNIQ_8D93D649E7927C74 ON "user" (email)
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE SCHEMA public
        SQL);
        $this->addSql(<<<'SQL'
            DROP SEQUENCE role_id_seq CASCADE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE user_roles DROP CONSTRAINT FK_54FCD59FA76ED395
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE user_roles DROP CONSTRAINT FK_54FCD59FD60322AC
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE role
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE user_roles
        SQL);
        $this->addSql(<<<'SQL'
            DROP INDEX UNIQ_8D93D649E7927C74
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE "user" ADD roles TEXT NOT NULL
        SQL);
        $this->addSql(<<<'SQL'
            COMMENT ON COLUMN "user".roles IS '(DC2Type:array)'
        SQL);
    }
}
