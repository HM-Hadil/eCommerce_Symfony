<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250515152404 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE "order" DROP payment_date
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE "order" ALTER reference TYPE VARCHAR(255)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE "order" ALTER shipping_address TYPE TEXT
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE "order" ALTER shipping_address SET NOT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE "order" ALTER billing_address TYPE TEXT
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE "order" ALTER billing_address SET NOT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE "order" ALTER payment_method TYPE VARCHAR(255)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE "order" RENAME COLUMN total_amount TO total
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE SCHEMA public
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE "order" ADD payment_date TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE "order" ALTER reference TYPE VARCHAR(20)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE "order" ALTER payment_method TYPE VARCHAR(20)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE "order" ALTER shipping_address TYPE VARCHAR(255)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE "order" ALTER shipping_address DROP NOT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE "order" ALTER billing_address TYPE VARCHAR(255)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE "order" ALTER billing_address DROP NOT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE "order" RENAME COLUMN total TO total_amount
        SQL);
        $this->addSql(<<<'SQL'
            COMMENT ON COLUMN "order".payment_date IS '(DC2Type:datetime_immutable)'
        SQL);
    }
}
