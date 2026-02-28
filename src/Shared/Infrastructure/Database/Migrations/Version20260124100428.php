<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Database\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260124100428 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add cart and car_item tables';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE SEQUENCE order_cart_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE SEQUENCE order_cart_item_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql(
            'CREATE TABLE order_cart (
                        id INT NOT NULL,
                        user_id INT DEFAULT NULL,
                        shop_num INT DEFAULT NULL,
                        order_date TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                        created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                        updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                        deleted_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
                        version INT DEFAULT 1 NOT NULL,
                        region INT NOT NULL,
                        is_delivery BOOLEAN DEFAULT false NOT NULL,
                        is_express BOOLEAN DEFAULT true NOT NULL,
                        total_cost NUMERIC(10, 2) NOT NULL,
                        PRIMARY KEY(id)
                 );'
        );
        $this->addSql('CREATE UNIQUE INDEX UNIQ_ACTIVE_CART_IDX ON order_cart (user_id) WHERE deleted_at IS NULL');
        $this->addSql('COMMENT ON COLUMN order_cart.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN order_cart.updated_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN order_cart.deleted_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN order_cart.order_date IS \'(DC2Type:datetime_immutable)\'');

        $this->addSql(
            'CREATE TABLE order_cart_item (
                                id INT NOT NULL,
                                cart_id INT NOT NULL,
                                sup_code VARCHAR(255) NOT NULL,
                                quantity INT NOT NULL,
                                created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                                updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                                per_item_price NUMERIC(10, 2) NOT NULL,
                                total_cost NUMERIC(10, 2) NOT NULL,
                                PRIMARY KEY(id)
                 );'
        );
        $this->addSql('CREATE INDEX IDX_4C795EDF1AD5CDBF ON order_cart_item (cart_id);');
        $this->addSql('COMMENT ON COLUMN order_cart_item.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN order_cart_item.updated_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql(
            'ALTER TABLE order_cart_item
                        ADD CONSTRAINT FK_4C795EDF1AD5CDBF FOREIGN KEY (cart_id) REFERENCES order_cart (id);'
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP SEQUENCE order_cart_id_seq CASCADE');
        $this->addSql('DROP SEQUENCE order_cart_item_id_seq CASCADE');
        $this->addSql('ALTER TABLE order_cart_item DROP CONSTRAINT FK_4C795EDF1AD5CDBF');
        $this->addSql('DROP TABLE order_cart');
        $this->addSql('DROP TABLE order_cart_item');
    }
}
