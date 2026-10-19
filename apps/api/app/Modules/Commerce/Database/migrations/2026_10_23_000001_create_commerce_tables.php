<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Commerce (PLAN §7, §8). Money is integer paise everywhere. Order items snapshot prices,
| GST and the commission rate at sale time so later changes never rewrite history.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coupons', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique(); // stored upper-case
            $table->string('kind', 20); // percent | fixed | fixed_price
            $table->unsignedInteger('value'); // percent 1–100, or paise
            $table->foreignId('instructor_id')->nullable()->constrained('users')->nullOnDelete(); // instructor's own coupon
            $table->foreignId('course_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('channel', 30)->default('organic');
            $table->unsignedInteger('max_redemptions')->nullable();
            $table->unsignedInteger('per_user_limit')->default(1);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('carts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->unique()->constrained()->cascadeOnDelete();
            $table->string('guest_token', 64)->nullable()->unique();
            $table->string('coupon_code', 40)->nullable();
            $table->timestamps();
        });

        Schema::create('cart_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cart_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['cart_id', 'course_id']);
        });

        Schema::create('wishlist_items', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->primary(['user_id', 'course_id']);
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('number', 30)->unique();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('status', 30)->default('pending')->index(); // pending | paid | failed | cancelled | partially_refunded | refunded
            $table->char('currency', 3)->default('INR');
            $table->unsignedBigInteger('subtotal');
            $table->unsignedBigInteger('discount_total')->default(0);
            $table->unsignedBigInteger('tax_total')->default(0);
            $table->unsignedBigInteger('total');
            $table->string('coupon_code', 40)->nullable();
            $table->string('gateway', 20);
            $table->string('gateway_order_id')->nullable()->unique();
            $table->timestamp('paid_at')->nullable();
            $table->json('billing_snapshot');
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->timestamps();
            $table->index(['user_id', 'created_at']);
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_id')->constrained()->restrictOnDelete();
            $table->foreignId('instructor_id')->constrained('users')->restrictOnDelete();
            $table->string('course_title');
            $table->unsignedBigInteger('list_price');
            $table->unsignedBigInteger('discount')->default(0);
            $table->unsignedBigInteger('amount'); // paid for this item, GST inclusive
            $table->unsignedBigInteger('tax');
            $table->unsignedBigInteger('net_amount'); // taxable value (amount − tax)
            $table->foreignId('coupon_id')->nullable()->constrained()->nullOnDelete();
            $table->string('channel', 30)->default('organic');
            $table->unsignedSmallInteger('commission_rate_bps'); // instructor share
            $table->unsignedBigInteger('gateway_fee')->default(0); // set on capture
            $table->unsignedBigInteger('instructor_amount')->default(0); // set on capture
            $table->unsignedBigInteger('platform_amount')->default(0);
            $table->unsignedBigInteger('refunded_amount')->default(0);
            $table->timestamps();
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->string('gateway', 20);
            $table->string('gateway_payment_id')->unique();
            $table->string('status', 20); // captured | failed
            $table->unsignedBigInteger('amount');
            $table->unsignedBigInteger('fee')->default(0);
            $table->unsignedBigInteger('tax_on_fee')->default(0);
            $table->string('method', 30)->nullable();
            $table->json('raw')->nullable();
            $table->timestamp('captured_at')->nullable();
            $table->timestamps();
        });

        Schema::create('refunds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_item_id')->constrained()->restrictOnDelete();
            $table->foreignId('payment_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('amount');
            $table->string('gateway_refund_id')->nullable()->unique();
            $table->string('status', 20)->index(); // requested | rejected | processing | processed | failed
            $table->string('reason', 1000);
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('auto_approved')->default(false);
            $table->string('decision_note', 1000)->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('coupon_redemptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('coupon_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['coupon_id', 'order_id']);
        });

        Schema::create('webhook_events', function (Blueprint $table) {
            $table->id();
            $table->string('gateway', 20);
            $table->string('event_id')->unique();
            $table->string('type', 60);
            $table->json('payload');
            $table->boolean('signature_valid');
            $table->string('status', 20)->default('pending'); // pending | processed | ignored | failed
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamp('received_at');
            $table->timestamp('processed_at')->nullable();
        });

        // Gapless invoice numbers per financial year (GST rule 46).
        Schema::create('invoice_sequences', function (Blueprint $table) {
            $table->string('financial_year', 7)->primary(); // 2026-27
            $table->unsignedInteger('last_number')->default(0);
        });

        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->unique()->constrained()->restrictOnDelete();
            $table->string('number', 40)->unique();
            $table->string('financial_year', 7);
            $table->string('file_path')->nullable();
            $table->json('seller');
            $table->json('buyer');
            $table->timestamp('issued_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['invoices', 'invoice_sequences', 'webhook_events', 'coupon_redemptions', 'refunds', 'payments', 'order_items', 'orders', 'wishlist_items', 'cart_items', 'carts', 'coupons'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
