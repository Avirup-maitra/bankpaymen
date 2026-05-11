<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('bank_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bank_file_id')->constrained()->onDelete('cascade');
            $table->integer('row_number');
            $table->string('import_status'); // OK, REJECTED
            $table->text('reject_reason')->nullable();

            // Normalized columns
            $table->string('transaction_type', 100)->nullable();
            $table->decimal('amount', 18, 2)->nullable();
            $table->string('debit_account_no', 50)->nullable();
            $table->string('ifsc', 20)->nullable();
            $table->string('beneficiary_account_no', 50)->nullable();
            $table->string('beneficiary_name', 255)->nullable();
            $table->text('remarks_for_client')->nullable();
            $table->text('remarks_for_beneficiary')->nullable();
            $table->string('transaction_id', 100)->nullable();
            $table->dateTime('transaction_date')->nullable();
            $table->string('invoice_id', 100)->nullable();
            $table->string('invoice_id_and_date', 150)->nullable();
            $table->string('token_id', 100)->nullable();
            $table->string('email_id', 255)->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('source_file_name', 255)->nullable();
            $table->string('file_name', 255)->nullable();
            $table->string('payment_ref_no', 100)->nullable();
            $table->string('bank_status', 50)->nullable();
            $table->dateTime('liquidation_date')->nullable();
            $table->string('customer_ref_no', 100)->nullable();
            $table->string('instrument_no', 100)->nullable();
            $table->text('utr_bank_remarks')->nullable();
            $table->string('maker_id', 100)->nullable();
            $table->string('first_approver', 100)->nullable();
            $table->string('second_approver', 100)->nullable();

            $table->json('payload_json')->nullable();

            $table->index('bank_status');
            $table->index('transaction_date');
            $table->index('payment_ref_no');
            $table->index('customer_ref_no');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bank_transactions');
    }
};