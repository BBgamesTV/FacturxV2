<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251003104259 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE facture ADD adresse_fournisseur VARCHAR(255) NOT NULL, ADD ville_fournisseur VARCHAR(255) NOT NULL, ADD code_postal_fournisseur VARCHAR(255) NOT NULL, ADD adresse_acheteur VARCHAR(255) NOT NULL, ADD ville_acheteur VARCHAR(255) NOT NULL, ADD code_postal_acheteur VARCHAR(255) NOT NULL, ADD code_pays_acheteur VARCHAR(2) NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE facture DROP adresse_fournisseur, DROP ville_fournisseur, DROP code_postal_fournisseur, DROP adresse_acheteur, DROP ville_acheteur, DROP code_postal_acheteur, DROP code_pays_acheteur');
    }
}
