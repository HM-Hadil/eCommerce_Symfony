<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250405212000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE product ADD is_active BOOLEAN DEFAULT NULL');
        
        // Set all existing products to active
        $this->addSql('UPDATE product SET is_active = true WHERE is_active IS NULL');
        
        // Make the column not nullable with a default value
        $this->addSql('ALTER TABLE product ALTER COLUMN is_active SET NOT NULL');
        $this->addSql('ALTER TABLE product ALTER COLUMN is_active SET DEFAULT true');
  
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE SCHEMA public
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE product DROP is_active
        SQL);
    }
}
