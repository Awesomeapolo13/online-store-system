<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Database\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260405171825 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE SEQUENCE order_order_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE SEQUENCE order_order_item_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE SEQUENCE order_status_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql(
            'CREATE TABLE order_order (
                    id INT NOT NULL,
                    status_id INT DEFAULT NULL,
                    user_id INT DEFAULT NULL,
                    shop_num INT DEFAULT NULL,
                    created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                    updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                    version INT DEFAULT 1 NOT NULL,
                    region INT NOT NULL,
                    is_delivery BOOLEAN NOT NULL,
                    is_express BOOLEAN NOT NULL,
                    order_date TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                    total_cost NUMERIC(10, 2) NOT NULL,
                    actual_total_cost NUMERIC(10, 2) NOT NULL,
                    PRIMARY KEY(id)
                )'
        );
        $this->addSql('CREATE INDEX IDX_76B7E7656BF700BD ON order_order (status_id)');
        $this->addSql('COMMENT ON COLUMN order_order.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN order_order.updated_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN order_order.order_date IS \'(DC2Type:datetime_immutable)\'');

        $this->addSql(
            'CREATE TABLE order_order_item (
                    id INT NOT NULL,
                    order_id INT NOT NULL,
                    sup_code VARCHAR(255) NOT NULL,
                    quantity INT NOT NULL,
                    actual_quantity INT NOT NULL,
                    created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                    updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                    per_item_price NUMERIC(10, 2) NOT NULL,
                    total_cost NUMERIC(10, 2) NOT NULL,
                    actual_total_cost NUMERIC(10, 2) NOT NULL,
                    PRIMARY KEY(id)
                )'
        );
        $this->addSql('CREATE INDEX IDX_E130E25C8D9F6D38 ON order_order_item (order_id)');
        $this->addSql('COMMENT ON COLUMN order_order_item.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN order_order_item.updated_at IS \'(DC2Type:datetime_immutable)\'');

        $this->addSql(
            'CREATE TABLE order_status (
                    id INT NOT NULL,
                    title VARCHAR(255) NOT NULL,
                    code VARCHAR(255) NOT NULL,
                    PRIMARY KEY(id)
                )'
        );
        $this->addSql('ALTER TABLE order_order ADD CONSTRAINT FK_76B7E7656BF700BD FOREIGN KEY (status_id) REFERENCES order_status (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE order_order_item ADD CONSTRAINT FK_E130E25C8D9F6D38 FOREIGN KEY (order_id) REFERENCES order_order (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP SEQUENCE order_order_id_seq CASCADE');
        $this->addSql('DROP SEQUENCE order_order_item_id_seq CASCADE');
        $this->addSql('DROP SEQUENCE order_status_id_seq CASCADE');
        $this->addSql('ALTER TABLE order_order DROP CONSTRAINT FK_76B7E7656BF700BD');
        $this->addSql('ALTER TABLE order_order_item DROP CONSTRAINT FK_E130E25C8D9F6D38');
        $this->addSql('DROP TABLE order_order');
        $this->addSql('DROP TABLE order_order_item');
        $this->addSql('DROP TABLE order_status');
    }
}
