<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250515161235 extends AbstractMigration
{
    public function getDescription(): string
    {
        // Updated description to reflect the changes
        return 'Add payment_status, payment_reference, paid_at columns to order table, rename total, and update order_item mapping.';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs

        // Step 1: Add the payment_status column as NULLABLE first
        $this->addSql('ALTER TABLE "order" ADD payment_status VARCHAR(50) DEFAULT NULL');

        // Step 2: Update existing rows to set a default value for payment_status
        // This is crucial to avoid the NOT NULL violation
        $this->addSql('UPDATE "order" SET payment_status = \'pending\' WHERE payment_status IS NULL');

        // Step 3: Alter the payment_status column to be NOT NULL
        $this->addSql('ALTER TABLE "order" ALTER COLUMN payment_status SET NOT NULL');


        // Keep the other statements from your original migration
        $this->addSql('ALTER TABLE "order" ADD payment_reference VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE "order" ADD paid_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE "order" RENAME COLUMN total TO total_amount');
        $this->addSql('COMMENT ON COLUMN "order".paid_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_F5299398AEA34913 ON "order" (reference)');
        $this->addSql('ALTER TABLE order_item DROP CONSTRAINT fk_52ea1f09e238517c');
        $this->addSql('DROP INDEX idx_52ea1f09e238517c');
        $this->addSql('ALTER TABLE order_item RENAME COLUMN order_ref_id TO order_id');
        $this->addSql('ALTER TABLE order_item ADD CONSTRAINT FK_52EA1F098D9F6D38 FOREIGN KEY (order_id) REFERENCES "order" (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_52EA1F098D9F6D38 ON order_item (order_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public'); // Keep this if it was in your original down()
        $this->addSql('ALTER TABLE order_item DROP CONSTRAINT FK_52EA1F098D9F6D38');
        $this->addSql('DROP INDEX IDX_52EA1F098D9F6D38');
        $this->addSql('ALTER TABLE order_item RENAME COLUMN order_id TO order_ref_id');
        $this->addSql('ALTER TABLE order_item ADD CONSTRAINT fk_52ea1f09e238517c FOREIGN KEY (order_ref_id) REFERENCES "order" (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX idx_52ea1f09e238517c ON order_item (order_ref_id)');
        $this->addSql('DROP INDEX UNIQ_F5299398AEA34913');
        // Note: When dropping columns, the order might matter if there are dependencies.
        // Dropping NOT NULL columns is usually fine.
        $this->addSql('ALTER TABLE "order" DROP payment_status');
        $this->addSql('ALTER TABLE "order" DROP payment_reference');
        $this->addSql('ALTER TABLE "order" DROP paid_at');
        $this->addSql('ALTER TABLE "order" RENAME COLUMN total_amount TO total');
    }
}
