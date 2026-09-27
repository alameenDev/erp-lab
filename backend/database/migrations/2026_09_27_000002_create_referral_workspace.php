<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void {
  Schema::create('referral_print_settings', function (Blueprint $t) {
   $t->id(); $t->foreignId('lab_id_fk')->unique()->constrained('users')->restrictOnDelete();
   foreach (['logo','report_background','lab_display_name','tagline','font_family','primary_color','secondary_color'] as $f) $t->string($f)->nullable();
   foreach (['print_margins','barcode_config','patient_header_config','print_table_config','document_config','loyalty_config','printer_config'] as $f) $t->json($f)->nullable();
   $t->text('ai_config')->nullable();
   foreach (['whatsapp_invoice_message','whatsapp_result_message'] as $f) $t->text($f)->nullable();
   foreach (['show_categories','show_tests_on_barcode','show_test_names','show_status'] as $f) $t->boolean($f)->default(true);
   foreach (['show_last_result','print_black_white'] as $f) $t->boolean($f)->default(false);
   $t->timestamps();
  });
  Schema::create('referral_invoice_details', function (Blueprint $t) {
   $t->id(); $t->foreignId('invoice_id')->unique()->constrained('invoices')->restrictOnDelete();
   $t->foreignId('referral_id')->constrained('users')->restrictOnDelete();
   $t->unsignedBigInteger('sub_total'); $t->unsignedBigInteger('total'); $t->unsignedBigInteger('paid')->default(0);
   $t->unsignedBigInteger('discount')->default(0); $t->unsignedInteger('discount_type_id_fk')->nullable();
   $t->json('payments')->nullable(); $t->text('notes')->nullable(); $t->timestamps();
  });
 }
 public function down(): void { Schema::dropIfExists('referral_invoice_details'); Schema::dropIfExists('referral_print_settings'); }
};
