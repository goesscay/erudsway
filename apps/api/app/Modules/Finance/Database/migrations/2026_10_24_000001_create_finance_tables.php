<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
| Marketplace finance (PLAN §7, §8). The ledger is insert-only: amounts are never edited
| or deleted (enforced here by triggers as well as in code); corrections are new entries.
| Payout and KYC details sit in their own table so finance PII stays in this module.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('commission_rules', function (Blueprint $table) {
            $table->id();
            $table->string('channel', 30); // organic | instructor_referral | platform_promoted
            $table->foreignId('instructor_id')->nullable()->constrained('users')->restrictOnDelete(); // null = default for everyone
            $table->unsignedSmallInteger('instructor_bps');
            $table->timestamp('effective_from');
            $table->timestamp('effective_to')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->index(['channel', 'instructor_id', 'effective_from']);
        });

        Schema::create('payout_accounts', function (Blueprint $table) {
            $table->foreignId('user_id')->primary()->constrained()->restrictOnDelete();
            $table->string('legal_name');
            $table->text('pan'); // encrypted
            $table->char('pan_last4', 4);
            $table->string('gstin', 15)->nullable();
            $table->string('account_holder');
            $table->text('account_number'); // encrypted
            $table->char('account_last4', 4);
            $table->string('ifsc', 11);
            $table->string('status', 20)->default('submitted'); // submitted | verified | rejected
            $table->string('review_note', 500)->nullable();
            $table->timestamp('submitted_at');
            $table->timestamp('verified_at')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('payouts', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('instructor_id')->constrained('users')->restrictOnDelete();
            $table->date('period_end');
            $table->bigInteger('gross');
            $table->unsignedBigInteger('tds');
            $table->string('tds_section', 10);
            $table->unsignedSmallInteger('tds_rate_bps');
            $table->bigInteger('net');
            $table->char('currency', 3)->default('INR');
            $table->string('status', 20)->default('draft')->index(); // draft | approved | paid | cancelled
            $table->string('method', 20)->default('bank_transfer');
            $table->json('bank_snapshot');
            $table->string('reference', 60)->nullable(); // bank UTR
            $table->string('note', 500)->nullable();
            $table->foreignId('generated_by')->nullable()->constrained('users')->nullOnDelete(); // null = the monthly schedule
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('paid_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });

        Schema::create('ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->uuid('txn_uuid')->index(); // entries of one event share it and sum to the money moved
            $table->string('account', 20); // instructor | platform | tax | gateway_fee
            $table->foreignId('instructor_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->bigInteger('amount'); // signed paise
            $table->char('currency', 3)->default('INR');
            $table->string('description', 200);
            $table->foreignId('order_item_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('refund_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('payout_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('status', 20); // pending | available | paid
            $table->timestamp('available_at')->nullable();
            $table->timestamps();
            $table->index(['instructor_id', 'account', 'status']);
            $table->index(['account', 'created_at']);
        });

        Schema::create('payout_items', function (Blueprint $table) {
            $table->foreignId('payout_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ledger_entry_id')->unique()->constrained()->restrictOnDelete(); // an entry is paid at most once
            $table->primary(['payout_id', 'ledger_entry_id']);
        });

        $this->protectLedger();
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared('DROP TRIGGER IF EXISTS ledger_entries_immutable ON ledger_entries; DROP FUNCTION IF EXISTS ledger_entries_immutable();');
        }
        Schema::dropIfExists('payout_items');
        Schema::dropIfExists('ledger_entries');
        Schema::dropIfExists('payouts');
        Schema::dropIfExists('payout_accounts');
        Schema::dropIfExists('commission_rules');
    }

    /** Reject deletes and any change to who/what/how much on a ledger entry. */
    private function protectLedger(): void
    {
        $message = 'ledger_entries are insert-only: record a correcting entry instead';

        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared(<<<SQL
                CREATE OR REPLACE FUNCTION ledger_entries_immutable() RETURNS trigger AS \$\$
                BEGIN
                    IF TG_OP = 'DELETE' THEN
                        RAISE EXCEPTION '{$message}';
                    END IF;
                    IF NEW.amount IS DISTINCT FROM OLD.amount OR NEW.account IS DISTINCT FROM OLD.account
                        OR NEW.instructor_id IS DISTINCT FROM OLD.instructor_id OR NEW.txn_uuid IS DISTINCT FROM OLD.txn_uuid
                        OR NEW.order_item_id IS DISTINCT FROM OLD.order_item_id OR NEW.refund_id IS DISTINCT FROM OLD.refund_id THEN
                        RAISE EXCEPTION '{$message}';
                    END IF;
                    RETURN NEW;
                END;
                \$\$ LANGUAGE plpgsql;
                CREATE TRIGGER ledger_entries_immutable BEFORE UPDATE OR DELETE ON ledger_entries
                    FOR EACH ROW EXECUTE FUNCTION ledger_entries_immutable();
                SQL);

            return;
        }

        if (DB::getDriverName() === 'sqlite') {
            DB::unprepared("CREATE TRIGGER ledger_entries_no_update BEFORE UPDATE OF amount, account, instructor_id, txn_uuid, order_item_id, refund_id ON ledger_entries BEGIN SELECT RAISE(ABORT, '{$message}'); END;");
            DB::unprepared("CREATE TRIGGER ledger_entries_no_delete BEFORE DELETE ON ledger_entries BEGIN SELECT RAISE(ABORT, '{$message}'); END;");
        }
    }
};
