<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251002092129 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE facture_ligne (id INT AUTO_INCREMENT NOT NULL, facture_id INT NOT NULL, designation VARCHAR(255) NOT NULL, reference VARCHAR(50) DEFAULT NULL, quantite NUMERIC(10, 2) NOT NULL, unite VARCHAR(20) DEFAULT NULL, prix_unitaire_ht NUMERIC(10, 2) NOT NULL, taux_tva NUMERIC(5, 2) NOT NULL, montant_ht NUMERIC(10, 2) NOT NULL, montant_tva NUMERIC(10, 2) NOT NULL, montant_ttc NUMERIC(10, 2) NOT NULL, INDEX IDX_C5C453347F2DEE08 (facture_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE facture_ligne ADD CONSTRAINT FK_C5C453347F2DEE08 FOREIGN KEY (facture_id) REFERENCES facture (id)');
        $this->addSql('DROP TABLE invoice_line');
        $this->addSql('DROP TABLE invoice');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE invoice_line (id INT AUTO_INCREMENT NOT NULL, line_id VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, global_id VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, seller_id VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, product_name VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, description LONGTEXT CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, characteristics JSON DEFAULT NULL, gross_price DOUBLE PRECISION DEFAULT NULL, net_price DOUBLE PRECISION DEFAULT NULL, quantity DOUBLE PRECISION NOT NULL, unit VARCHAR(10) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, tax_rate DOUBLE PRECISION DEFAULT NULL, tax_category VARCHAR(2) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, allowances JSON DEFAULT NULL, note LONGTEXT CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE invoice (id INT AUTO_INCREMENT NOT NULL, invoice_number VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, issue_date DATETIME NOT NULL, buyer JSON DEFAULT NULL, seller JSON DEFAULT NULL, tax_representative JSON DEFAULT NULL, payment_terms JSON DEFAULT NULL, totals JSON DEFAULT NULL, currency VARCHAR(3) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, payment_reference VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, note VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('ALTER TABLE facture_ligne DROP FOREIGN KEY FK_C5C453347F2DEE08');
        $this->addSql('DROP TABLE facture_ligne');
    }
}
