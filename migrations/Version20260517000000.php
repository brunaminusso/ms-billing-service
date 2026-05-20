<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260517000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Initial schema for Billing Service.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            "CREATE TABLE IF NOT EXISTS quotations (\n" .
                "    id INT AUTO_INCREMENT PRIMARY KEY,\n" .
                "    service_order_id INT NOT NULL,\n" .
                "    status VARCHAR(30) NOT NULL,\n" .
                "    total_in_cents INT NOT NULL,\n" .
                "    currency VARCHAR(3) NOT NULL,\n" .
                "    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,\n" .
                "    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,\n" .
                "    INDEX idx_service_order (service_order_id),\n" .
                "    INDEX idx_status (status)\n" .
                ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        $this->addSql(
            "CREATE TABLE IF NOT EXISTS payments (\n" .
                "    id INT AUTO_INCREMENT PRIMARY KEY,\n" .
                "    quotation_id INT NOT NULL,\n" .
                "    provider VARCHAR(40) NOT NULL,\n" .
                "    provider_payment_id VARCHAR(100) NULL,\n" .
                "    status VARCHAR(30) NOT NULL,\n" .
                "    amount_in_cents INT NOT NULL,\n" .
                "    currency VARCHAR(3) NOT NULL,\n" .
                "    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,\n" .
                "    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,\n" .
                "    INDEX idx_quotation (quotation_id),\n" .
                "    INDEX idx_provider_payment (provider_payment_id),\n" .
                "    INDEX idx_status (status)\n" .
                ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        $this->addSql(
            "CREATE TABLE IF NOT EXISTS payment_events (\n" .
                "    id INT AUTO_INCREMENT PRIMARY KEY,\n" .
                "    payment_id INT NOT NULL,\n" .
                "    event_type VARCHAR(100) NOT NULL,\n" .
                "    payload JSON NOT NULL,\n" .
                "    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,\n" .
                "    INDEX idx_payment (payment_id),\n" .
                "    INDEX idx_event_type (event_type)\n" .
                ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS payment_events');
        $this->addSql('DROP TABLE IF EXISTS payments');
        $this->addSql('DROP TABLE IF EXISTS quotations');
    }
}
