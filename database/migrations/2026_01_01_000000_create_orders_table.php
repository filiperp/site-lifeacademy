<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();

            // Referência pública do pedido (LA-2026-000123). Vira o
            // externalReference no Asaas e o número da compra na la-app.
            $table->string('reference')->unique();
            $table->uuid('uuid')->unique();

            $table->string('status')->default('pending')->index();

            $table->string('customer_name');
            $table->string('customer_email')->index();
            $table->string('customer_phone')->nullable();
            $table->string('customer_document')->nullable();
            $table->string('locale', 5)->default('pt_BR');

            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('discount', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->string('currency', 3)->default('BRL');

            // Asaas
            $table->string('asaas_checkout_id')->nullable()->unique();
            $table->text('asaas_checkout_url')->nullable();
            $table->string('asaas_payment_id')->nullable()->index();
            $table->string('asaas_customer_id')->nullable();
            $table->string('billing_type')->nullable();
            $table->unsignedSmallInteger('installment_count')->nullable();
            $table->decimal('installment_value', 12, 2)->nullable();
            $table->timestamp('paid_at')->nullable();

            // Entrega para a API da Life Academy
            $table->string('delivery_status')->default('pending')->index();
            $table->unsignedSmallInteger('delivery_attempts')->default(0);
            $table->timestamp('delivered_at')->nullable();
            $table->text('delivery_error')->nullable();
            $table->text('delivery_response')->nullable();

            $table->timestamps();
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();

            $table->string('product_key');
            $table->string('variant_key');
            $table->string('name');

            // Codenames dos bundles entregues por este item, em JSON.
            // Viram o SKU (unidos por "-") enviado à API da Life Academy.
            $table->json('codenames');

            $table->unsignedInteger('quantity')->default(1);
            $table->decimal('list_price', 12, 2);
            $table->decimal('price', 12, 2);
            $table->decimal('subtotal', 12, 2);
            $table->decimal('total', 12, 2);

            $table->timestamps();
        });

        Schema::create('asaas_webhook_events', function (Blueprint $table) {
            $table->id();
            $table->string('event_id')->unique();
            $table->string('event');
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->json('payload');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asaas_webhook_events');
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
    }
};
