<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations - All tables in a single file for PostgreSQL compatibility.
     */
    public function up(): void
    {
        // ============================================
        // 1. PERMISSION TABLES (Spatie)
        // ============================================
        Schema::create('permissions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name');
            $table->string('guard_name');
            $table->timestamps();
            $table->unique(['name', 'guard_name']);
        });

        Schema::create('roles', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name');
            $table->string('guard_name');
            $table->unsignedBigInteger('lab_id_fk')->nullable();
            $table->timestamps();
            $table->unique(['name', 'guard_name']);
        });

        Schema::create('model_has_permissions', function (Blueprint $table) {
            $table->unsignedBigInteger('permission_id');
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');
            $table->index(['model_id', 'model_type'], 'model_has_permissions_model_id_model_type_index');
            $table->foreign('permission_id')->references('id')->on('permissions')->onDelete('cascade');
            $table->primary(['permission_id', 'model_id', 'model_type'], 'model_has_permissions_permission_model_type_primary');
        });

        Schema::create('model_has_roles', function (Blueprint $table) {
            $table->unsignedBigInteger('role_id');
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');
            $table->index(['model_id', 'model_type'], 'model_has_roles_model_id_model_type_index');
            $table->foreign('role_id')->references('id')->on('roles')->onDelete('cascade');
            $table->primary(['role_id', 'model_id', 'model_type'], 'model_has_roles_role_model_type_primary');
        });

        Schema::create('role_has_permissions', function (Blueprint $table) {
            $table->unsignedBigInteger('permission_id');
            $table->unsignedBigInteger('role_id');
            $table->foreign('permission_id')->references('id')->on('permissions')->onDelete('cascade');
            $table->foreign('role_id')->references('id')->on('roles')->onDelete('cascade');
            $table->primary(['permission_id', 'role_id'], 'role_has_permissions_permission_id_role_id_primary');
        });

        // ============================================
        // 2. USERS TABLE
        // ============================================
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->unsignedBigInteger('creator_id')->nullable();
            $table->string('image')->nullable();
            $table->string('phone_number')->nullable();
            $table->string('address')->nullable();
            $table->string('signiture')->nullable();
            $table->unsignedBigInteger('role_id')->nullable();
            $table->tinyInteger('status')->default(1);
            $table->softDeletes();
            $table->rememberToken();
            $table->timestamps();
        });

        // Self-referencing foreign key
        Schema::table('users', function (Blueprint $table) {
            $table->foreign('creator_id')->references('id')->on('users')->onDelete('cascade');
        });

        // Add lab_id_fk FK to roles (must be after users table exists)
        Schema::table('roles', function (Blueprint $table) {
            $table->foreign('lab_id_fk')->references('id')->on('users')->onDelete('cascade');
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });

        // ============================================
        // 3. CACHE TABLES
        // ============================================
        Schema::create('cache', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->mediumText('value');
            $table->integer('expiration');
        });

        Schema::create('cache_locks', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->string('owner');
            $table->integer('expiration');
        });

        // ============================================
        // 4. JOBS TABLES
        // ============================================
        Schema::create('jobs', function (Blueprint $table) {
            $table->id();
            $table->string('queue')->index();
            $table->longText('payload');
            $table->unsignedTinyInteger('attempts');
            $table->unsignedInteger('reserved_at')->nullable();
            $table->unsignedInteger('available_at');
            $table->unsignedInteger('created_at');
        });

        Schema::create('job_batches', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('name');
            $table->integer('total_jobs');
            $table->integer('pending_jobs');
            $table->integer('failed_jobs');
            $table->longText('failed_job_ids');
            $table->mediumText('options')->nullable();
            $table->integer('cancelled_at')->nullable();
            $table->integer('created_at');
            $table->integer('finished_at')->nullable();
        });

        Schema::create('failed_jobs', function (Blueprint $table) {
            $table->id();
            $table->string('uuid')->unique();
            $table->text('connection');
            $table->text('queue');
            $table->longText('payload');
            $table->longText('exception');
            $table->timestamp('failed_at')->useCurrent();
        });

        // ============================================
        // 5. PERSONAL ACCESS TOKENS (Sanctum)
        // ============================================
        Schema::create('personal_access_tokens', function (Blueprint $table) {
            $table->id();
            $table->morphs('tokenable');
            $table->string('name');
            $table->string('token', 64)->unique();
            $table->text('abilities')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });

        // ============================================
        // 6. VERIFY CODES
        // ============================================
        Schema::create('verify_codes', function (Blueprint $table) {
            $table->id();
            $table->string('email');
            $table->string('code');
            $table->timestamps();
        });

        // ============================================
        // 7. ACTIVITY LOG
        // ============================================
        Schema::create('activity_log', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('log_name')->nullable();
            $table->text('description');
            $table->foreignId('creator_id')->nullable()->constrained('users');
            $table->foreignId('parent_id')->nullable()->constrained('users');
            $table->nullableMorphs('subject', 'subject');
            $table->string('event')->nullable();
            $table->nullableMorphs('causer', 'causer');
            $table->json('properties')->nullable();
            $table->uuid('batch_uuid')->nullable();
            $table->timestamps();
            $table->index('log_name');
        });

        // ============================================
        // 8. LOOKUP TABLES
        // ============================================
        Schema::create('titles', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('genders', function (Blueprint $table) {
            $table->id();
            $table->string('gender_type');
            $table->timestamps();
        });

        Schema::create('nationalities', function (Blueprint $table) {
            $table->id();
            $table->string('country_name');
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('age_units', function (Blueprint $table) {
            $table->id();
            $table->string('unit_name');
            $table->timestamps();
        });

        Schema::create('duration_units', function (Blueprint $table) {
            $table->id();
            $table->string('unit');
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('result_types', function (Blueprint $table) {
            $table->id();
            $table->string('result_type_name')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('answer_types', function (Blueprint $table) {
            $table->id();
            $table->string('answer');
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('result_statuses', function (Blueprint $table) {
            $table->id();
            $table->string('status');
            $table->timestamps();
        });

        // ============================================
        // 9. CONTRACTS
        // ============================================
        Schema::create('contracts', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->bigInteger('payment_percent')->nullable();
            $table->bigInteger('maximum_payment_per_invoice')->nullable();
            $table->bigInteger('credit_limit')->nullable();
            $table->bigInteger('price_limit')->nullable();
            $table->bigInteger('discount_percentage')->nullable();
            $table->string('address')->nullable();
            $table->string('phone_number')->nullable();
            $table->string('email')->nullable();
            $table->string('password')->nullable();
            $table->foreignId('lab_id_fk')->nullable()->constrained('users');
            $table->softDeletes();
            $table->timestamps();
        });

        // ============================================
        // 10. PATIENTS
        // ============================================
        Schema::create('patients', function (Blueprint $table) {
            $table->id();
            $table->string('code')->nullable();
            $table->foreignId('title_id_fk')->nullable()->constrained('titles')->onDelete('cascade');
            $table->string('image')->nullable();
            $table->string('lab_card')->nullable();
            $table->foreignId('contract_id_fk')->nullable()->constrained('contracts')->onDelete('cascade');
            $table->string('address')->nullable();
            $table->foreignId('nationality_id_fk')->nullable()->references('id')->on('nationalities')->onDelete('cascade');
            $table->date('dob')->nullable();
            $table->foreignId('gender_id_fk')->nullable()->references('id')->on('genders')->onDelete('cascade');
            $table->bigInteger('age')->nullable();
            $table->foreignId('age_unit_id_fk')->nullable()->references('id')->on('age_units')->onDelete('cascade');
            $table->string('passport_no')->nullable();
            $table->bigInteger('national_id_no')->nullable();
            $table->string('barcode')->nullable();
            $table->foreignId('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreignId('parent_id')->nullable()->references('id')->on('users')->onDelete('cascade');
            $table->foreignId('creator_id')->nullable()->references('id')->on('users')->onDelete('cascade');
            $table->softDeletes();
            $table->timestamps();
        });

        // ============================================
        // 11. PRICE LISTS
        // ============================================
        Schema::create('price_lists', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('lab_id_fk')->constrained('users');
            $table->bigInteger('discount')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        // ============================================
        // 12. REFERRALS
        // ============================================
        Schema::create('rel_labs_referals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lab_id_fk')->references('id')->on('users')->onDelete('cascade');
            $table->foreignId('referral_id_fk')->references('id')->on('users')->onDelete('cascade');
            $table->bigInteger('commission')->nullable()->unsigned();
            $table->foreignId('price_list_id_fk')->nullable()->references('id')->on('price_lists')->onDelete('cascade');
            $table->softDeletes();
            $table->timestamps();
            $table->unique(['lab_id_fk', 'referral_id_fk'], 'unique_lab_referral');
        });

        // ============================================
        // 13. LABS
        // ============================================
        Schema::create('labs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id_fk')->references('id')->on('users')->onDelete('cascade');
            $table->foreignId('parent_lab_id_fk')->nullable()->references('id')->on('users')->onDelete('cascade');
            $table->foreignId('sample_collector_id_fk')->nullable()->references('id')->on('users')->onDelete('cascade');
            $table->bigInteger('discount_percentage')->unsigned()->nullable();
            $table->foreignId('price_list_id_fk')->nullable()->references('id')->on('price_lists')->onDelete('cascade');
            $table->softDeletes();
            $table->timestamps();
        });

        // ============================================
        // 14. CATEGORIES & SAMPLES
        // ============================================
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lab_id_fk')->references('id')->on('users')->cascadeOnDelete();
            $table->string('name')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('samples', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lab_id_fk')->references('id')->on('users')->cascadeOnDelete();
            $table->string('sample_name')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        // ============================================
        // 15. TEST GROUPS
        // ============================================
        Schema::create('test_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lab_id_fk')->references('id')->on('users');
            $table->foreignId('category_id_fk')->nullable()->references('id')->on('categories')->cascadeOnDelete();
            $table->string('group_name')->nullable();
            $table->string('shortcut')->nullable();
            $table->foreignId('sample_id_fk')->nullable()->references('id')->on('samples');
            $table->bigInteger('original_price')->nullable();
            $table->bigInteger('for_customer_price')->nullable();
            $table->bigInteger('test_duration')->nullable();
            $table->foreignId('duration_unit_id_fk')->nullable()->references('id')->on('duration_units');
            $table->longText('precautions')->nullable();
            $table->boolean('is_print_alone')->nullable();
            $table->json('result_comments')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        // ============================================
        // 16. CULTURES
        // ============================================
        Schema::create('cultures', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->longText('precautions')->nullable();
            $table->json('result_comments')->nullable();
            $table->foreignId('category_id_fk')->nullable()->references('id')->on('categories')->cascadeOnDelete();
            $table->foreignId('lab_id_fk')->references('id')->on('users')->cascadeOnDelete();
            $table->bigInteger('price')->nullable();
            $table->bigInteger('price_for_customer')->nullable();
            $table->foreignId('test_group_id_fk')->nullable()->references('id')->on('test_groups');
            $table->foreignId('sample_id_fk')->nullable()->references('id')->on('samples');
            $table->bigInteger('test_duration')->nullable();
            $table->foreignId('duration_unit_id_fk')->nullable()->references('id')->on('duration_units');
            $table->softDeletes();
            $table->timestamps();
        });

        // ============================================
        // 17. TESTS
        // ============================================
        Schema::create('tests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('test_group_id_fk')->nullable()->references('id')->on('test_groups');
            $table->foreignId('category_id_fk')->nullable()->references('id')->on('categories')->cascadeOnDelete();
            $table->string('name')->nullable();
            $table->foreignId('lab_id_fk')->nullable()->references('id')->on('users')->cascadeOnDelete();
            $table->bigInteger('order')->nullable();
            $table->string('shortcut')->nullable();
            $table->string('report_name')->nullable();
            $table->string('interface_code')->nullable();
            $table->string('unit')->nullable();
            $table->foreignId('sample_id_fk')->nullable()->references('id')->on('samples');
            $table->bigInteger('test_duration')->nullable();
            $table->foreignId('duration_unit_id_fk')->nullable()->references('id')->on('duration_units');
            $table->foreignId('result_type_id_fk')->nullable()->references('id')->on('result_types');
            $table->json('selection_type_options')->nullable();
            $table->boolean('is_contain_status')->nullable();
            $table->bigInteger('price')->nullable();
            $table->bigInteger('for_customer_price')->nullable();
            $table->boolean('is_print_alone')->nullable();
            $table->json('result_comments')->nullable();
            $table->boolean('is_special_test')->default(false);
            $table->jsonb('content')->nullable();
            $table->jsonb('sub_tests')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        // ============================================
        // 18. PATIENT QUESTIONS
        // ============================================
        Schema::create('patient_questions', function (Blueprint $table) {
            $table->id();
            $table->string('question');
            $table->foreignId('lab_id_fk')->references('id')->on('users')->onDelete('cascade');
            $table->foreignId('answer_type_id_fk')->references('id')->on('answer_types')->onDelete('cascade');
            $table->json('answer_type_selection_values')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        // ============================================
        // 19. TEST REFERENCE RANGES
        // ============================================
        Schema::create('test_reference_range', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lab_id_fk')->references('id')->on('users')->cascadeOnDelete();
            $table->foreignId('test_id')->references('id')->on('tests');
            $table->foreignId('gender_id_fk')->references('id')->on('genders')->cascadeOnDelete();
            $table->foreignId('age_unit_id_fk')->references('id')->on('age_units')->cascadeOnDelete();
            $table->bigInteger('age_from')->nullable();
            $table->bigInteger('age_to')->nullable();
            $table->string('from')->nullable();
            $table->string('to')->nullable();
            $table->string('notes')->nullable();
            $table->json('selection_type_options')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        // ============================================
        // 20. TEST GROUP COMMENTS
        // ============================================
        Schema::create('test_groups_comment', function (Blueprint $table) {
            $table->id();
            $table->foreignId('test_group_id_fk')->references('id')->on('test_groups')->cascadeOnDelete();
            $table->foreignId('lab_id_fk')->references('id')->on('users')->cascadeOnDelete();
            $table->string('comment')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        // ============================================
        // 21. TEST QUESTION RELATIONS
        // ============================================
        Schema::create('test_questions_rel', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lab_id_fk')->references('id')->on('users')->cascadeOnDelete();
            $table->foreignId('test_id_fk')->references('id')->on('tests')->onDelete('cascade');
            $table->foreignId('question_id_fk')->references('id')->on('patient_questions')->onDelete('cascade');
            $table->softDeletes();
            $table->timestamps();
        });

        // ============================================
        // 22. PACKAGES
        // ============================================
        Schema::create('packages', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('lab_id_fk')->constrained('users');
            $table->string('shortcut')->nullable();
            $table->bigInteger('price')->nullable();
            $table->boolean('is_constant_price')->default(false);
            $table->softDeletes();
            $table->timestamps();
        });

        // ============================================
        // 23. PRICE LIST RELATIONS
        // ============================================
        Schema::create('price_list_rels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lab_id_fk')->references('id')->on('users')->cascadeOnDelete();
            $table->foreignId('price_list_id_fk')->references('id')->on('price_lists')->cascadeOnDelete();
            $table->foreignId('test_group_id_fk')->nullable()->references('id')->on('test_groups')->cascadeOnDelete();
            $table->foreignId('culture_id_fk')->nullable()->references('id')->on('cultures')->cascadeOnDelete();
            $table->foreignId('package_id_fk')->nullable()->references('id')->on('packages')->cascadeOnDelete();
            $table->foreignId('test_id_fk')->nullable()->references('id')->on('tests')->cascadeOnDelete();
            $table->bigInteger('original_price')->nullable();
            $table->bigInteger('price_for_customer')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        // ============================================
        // 24. TEST PACKAGE RELATIONS
        // ============================================
        Schema::create('test_package_rel', function (Blueprint $table) {
            $table->id();
            $table->foreignId('test_id_fk')->references('id')->on('tests')->cascadeOnDelete();
            $table->foreignId('package_id_fk')->references('id')->on('packages')->cascadeOnDelete();
            $table->timestamps();
        });

        // ============================================
        // 25. CULTURE PACKAGE RELATIONS
        // ============================================
        Schema::create('culture_package_rel', function (Blueprint $table) {
            $table->id();
            $table->foreignId('culture_id_fk')->references('id')->on('cultures')->cascadeOnDelete();
            $table->foreignId('package_id_fk')->references('id')->on('packages')->cascadeOnDelete();
            $table->timestamps();
        });

        // ============================================
        // 26. ATTRIBUTES
        // ============================================
        Schema::create('attributes', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->bigInteger('order')->nullable();
            $table->foreignId('result_type_id_fk')->nullable()->references('id')->on('result_types')->onDelete('cascade');
            $table->foreignId('culture_id_fk')->nullable()->references('id')->on('cultures')->onDelete('cascade');
            $table->json('selection_type_options')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        // ============================================
        // 27. ANTIBIOTICS
        // ============================================
        Schema::create('antibiotics', function (Blueprint $table) {
            $table->id();
            $table->string('scientific_name')->nullable();
            $table->string('common_name')->nullable();
            $table->string('short_name')->nullable();
            $table->foreignId('lab_id')->constrained('users')->onDelete('cascade');
            $table->softDeletes();
            $table->timestamps();
        });

        // ============================================
        // 28. PAYMENT METHODS
        // ============================================
        Schema::create('payment_methods', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedBigInteger('lab_id_fk');
            $table->unsignedBigInteger('invoice_id_fk')->nullable();
            $table->unsignedBigInteger('contract_id_fk')->nullable();
            $table->bigInteger('amount')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->foreign('lab_id_fk')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('contract_id_fk')->references('id')->on('contracts')->onDelete('cascade');
        });

        // ============================================
        // 29. DISCOUNT TYPES
        // ============================================
        Schema::create('discount_types', function (Blueprint $table) {
            $table->id();
            $table->string('type')->nullable();
            $table->unsignedBigInteger('lab_id_fk')->nullable();
            $table->unsignedBigInteger('invoice_id_fk')->nullable();
            $table->unsignedBigInteger('contract_id_fk')->nullable();
            $table->bigInteger('amount')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->foreign('lab_id_fk')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('contract_id_fk')->references('id')->on('contracts')->onDelete('cascade');
        });

        // ============================================
        // 30. INVOICES
        // ============================================
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id_fk')->nullable()->references('id')->on('patients')->onDelete('set null');
            $table->foreignId('from_lab_id_fk')->nullable()->references('id')->on('users')->onDelete('set null');
            $table->foreignId('sample_collector_id_fk')->nullable()->references('id')->on('users')->onDelete('set null');
            $table->foreignId('contract_id_fk')->nullable()->references('id')->on('contracts')->onDelete('set null');
            $table->foreignId('referral_id_fk')->nullable()->references('id')->on('users')->onDelete('set null');
            $table->timestamp('registration_date')->nullable();
            $table->timestamp('result_date')->nullable();
            $table->boolean('show_result_date')->default(false);
            $table->boolean('show_patient_card_id')->default(false);
            $table->boolean('show_patient_pic')->default(false);
            $table->boolean('sent_to_patient')->default(false);
            $table->boolean('is_printed')->nullable()->default(false);
            $table->boolean('is_signed')->nullable()->default(false);
            $table->foreignId('signed_by_id_fk')->nullable()->references('id')->on('users')->onDelete('set null');
            $table->boolean('is_done')->nullable()->default(false);
            $table->string('qr_code')->nullable();
            $table->json('attachments')->nullable();
            $table->bigInteger('sub_total')->nullable();
            $table->bigInteger('discount')->nullable();
            $table->foreignId('discount_type_id_fk')->nullable()->references('id')->on('discount_types')->onDelete('set null');
            $table->bigInteger('total')->nullable();
            $table->bigInteger('paid')->nullable();
            $table->string('notes')->nullable();
            $table->string('result_doc')->nullable();
            $table->string('barcode')->nullable();
            $table->string('pdf_qr_code')->nullable();
            $table->json('tests_comment')->nullable();
            $table->json('cultures_comment')->nullable();
            $table->json('packages_comment')->nullable();
            $table->foreignId('lab_id_fk')->references('id')->on('users')->onDelete('cascade');
            $table->timestamps();
            $table->softDeletes();
        });

        // ============================================
        // 31. INVOICE PAID DETAILS
        // ============================================
        Schema::create('invoice_paid_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lab_id_fk')->nullable()->references('id')->on('users')->onDelete('cascade');
            $table->foreignId('invoice_id_fk')->nullable()->references('id')->on('invoices')->onDelete('cascade');
            $table->foreignId('payment_method_id_fk')->nullable()->references('id')->on('payment_methods');
            $table->foreignId('contract_id_fk')->nullable()->references('id')->on('contracts')->onDelete('set null');
            $table->bigInteger('amount')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        // ============================================
        // 32. INVOICE TEST RELATIONS
        // ============================================
        Schema::create('invoice_test_rels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id_fk')->references('id')->on('invoices')->onDelete('cascade');
            $table->foreignId('test_id_fk')->nullable()->references('id')->on('tests')->onDelete('set null');
            $table->foreignId('culture_id_fk')->nullable()->references('id')->on('cultures')->onDelete('set null');
            $table->foreignId('test_group_id_fk')->nullable()->references('id')->on('test_groups')->onDelete('set null');
            $table->foreignId('package_id_fk')->nullable()->references('id')->on('packages')->onDelete('set null');
            $table->foreignId('result_status_id_fk')->nullable()->references('id')->on('result_statuses')->onDelete('set null');
            $table->foreignId('to_lab_id_fk')->nullable()->references('id')->on('users')->onDelete('set null');
            $table->json('questions')->nullable();
            $table->json('attribute')->nullable();
            $table->boolean('is_sample_received')->default(false);
            $table->bigInteger('price')->nullable();
            $table->boolean('last_result')->nullable();
            $table->json('package_tests')->nullable();
            $table->json('package_cultures')->nullable();
            $table->json('test_group_tests')->nullable();
            $table->json('test_group_cultures')->nullable();
            $table->json('last_result_data')->nullable();
            $table->string('result')->nullable();
            $table->string('comment')->nullable();
            $table->boolean('is_done')->default(false)->nullable();
            $table->boolean('is_special_test')->default(false);
            $table->jsonb('content')->nullable();
            $table->jsonb('sub_tests')->nullable();
            $table->timestamps();

            // Unique constraints for upsert operations
            $table->unique(['invoice_id_fk', 'test_id_fk'], 'invoice_test_unique');
            $table->unique(['invoice_id_fk', 'culture_id_fk'], 'invoice_culture_unique');
            $table->unique(['invoice_id_fk', 'package_id_fk'], 'invoice_package_unique');
            $table->unique(['invoice_id_fk', 'test_group_id_fk'], 'invoice_test_group_unique');
        });

        // ============================================
        // 33. BOOKINGS
        // ============================================
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id_fk')->constrained('patients');
            $table->string('prescription')->nullable();
            $table->longText('required_tests')->nullable();
            $table->foreignId('lab_id_fk')->constrained('users')->onDelete('cascade');
            $table->string('address')->nullable();
            $table->boolean('at_home')->default(false);
            $table->string('lng')->nullable();
            $table->string('lat')->nullable();
            $table->timestamp('booking_date')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        // ============================================
        // 34. BOOKING TEST RELATIONS
        // ============================================
        Schema::create('booking_test_rel', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id_fk')->nullable()->constrained('bookings');
            $table->foreignId('test_id_fk')->nullable()->constrained('tests');
            $table->foreignId('culture_id_fk')->nullable()->constrained('cultures');
            $table->foreignId('package_id_fk')->nullable()->constrained('packages');
        });

        // ============================================
        // 35. TEMPLATES
        // ============================================
        Schema::create('templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->jsonb('content');
            $table->foreignId('test_id_fk')->nullable()->constrained('tests');
            $table->foreignId('culture_id_fk')->nullable()->constrained('cultures');
            $table->foreignId('package_id_fk')->nullable()->constrained('packages');
            $table->foreignId('test_group_id_fk')->nullable()->constrained('test_groups');
            $table->unsignedBigInteger('lab_id_fk')->nullable();
            $table->foreign('lab_id_fk')->references('id')->on('users')->nullOnDelete();
            $table->string('type');
            $table->timestamps();
        });

        // ============================================
        // 36. ALL INDEXES (Optimized - no redundant indexes)
        // ============================================

        // Users indexes
        Schema::table('users', function (Blueprint $table) {
            $table->index('role_id', 'users_role_id_idx');
            $table->index('creator_id', 'users_creator_id_idx');
            $table->index(['deleted_at', 'role_id'], 'users_deleted_role_idx');
            $table->index('phone_number', 'users_phone_number_idx');
        });

        // Contracts indexes
        Schema::table('contracts', function (Blueprint $table) {
            $table->index('lab_id_fk', 'contracts_lab_id_fk_idx');
            $table->index(['deleted_at', 'lab_id_fk'], 'contracts_deleted_lab_idx');
        });

        // Patients indexes
        Schema::table('patients', function (Blueprint $table) {
            $table->index('code');
            $table->index('barcode');
            $table->index('user_id');
            $table->index('national_id_no');
            $table->index('title_id_fk', 'patients_title_id_fk_idx');
            $table->index('contract_id_fk', 'patients_contract_id_fk_idx');
            $table->index('gender_id_fk', 'patients_gender_id_fk_idx');
            $table->index('creator_id', 'patients_creator_id_idx');
            $table->index('parent_id', 'patients_parent_id_idx');
            $table->index(['deleted_at', 'creator_id'], 'patients_deleted_creator_idx');
            $table->index(['creator_id', 'created_at'], 'patients_creator_created_idx');
            $table->index(['parent_id', 'created_at'], 'patients_parent_created_idx');
        });

        // Price lists indexes
        Schema::table('price_lists', function (Blueprint $table) {
            $table->index('lab_id_fk', 'price_lists_lab_id_fk_idx');
        });

        // Labs indexes
        Schema::table('labs', function (Blueprint $table) {
            $table->index('user_id_fk', 'labs_user_id_fk_idx');
            $table->index('parent_lab_id_fk', 'labs_parent_lab_id_fk_idx');
            $table->index('price_list_id_fk', 'labs_price_list_id_fk_idx');
        });

        // Categories indexes
        Schema::table('categories', function (Blueprint $table) {
            $table->index('lab_id_fk', 'categories_lab_id_fk_idx');
            $table->index(['deleted_at', 'lab_id_fk'], 'categories_deleted_lab_idx');
        });

        // Samples indexes
        Schema::table('samples', function (Blueprint $table) {
            $table->index('lab_id_fk', 'samples_lab_id_fk_idx');
            $table->index(['deleted_at', 'lab_id_fk'], 'samples_deleted_lab_idx');
        });

        // Test groups indexes
        Schema::table('test_groups', function (Blueprint $table) {
            $table->index('lab_id_fk', 'test_groups_lab_id_fk_idx');
            $table->index('category_id_fk', 'test_groups_category_id_fk_idx');
            $table->index('sample_id_fk', 'test_groups_sample_id_fk_idx');
            $table->index(['deleted_at', 'lab_id_fk'], 'test_groups_deleted_lab_idx');
        });

        // Cultures indexes
        Schema::table('cultures', function (Blueprint $table) {
            $table->index('lab_id_fk');
            $table->index('name');
            $table->index('category_id_fk');
            $table->index('sample_id_fk', 'cultures_sample_id_fk_idx');
            $table->index('test_group_id_fk', 'cultures_test_group_id_fk_idx');
            $table->index(['deleted_at', 'lab_id_fk'], 'cultures_deleted_lab_idx');
        });

        // Tests indexes
        Schema::table('tests', function (Blueprint $table) {
            $table->index('lab_id_fk');
            $table->index('name');
            $table->index('category_id_fk');
            $table->index('test_group_id_fk');
            $table->index('sample_id_fk', 'tests_sample_id_fk_idx');
            $table->index('result_type_id_fk', 'tests_result_type_id_fk_idx');
            $table->index('shortcut', 'tests_shortcut_idx');
            $table->index(['deleted_at', 'lab_id_fk'], 'tests_deleted_lab_idx');
        });

        // Patient questions indexes
        Schema::table('patient_questions', function (Blueprint $table) {
            $table->index('lab_id_fk', 'patient_questions_lab_id_fk_idx');
            $table->index('answer_type_id_fk', 'patient_questions_answer_type_id_fk_idx');
        });

        // Test reference range indexes
        Schema::table('test_reference_range', function (Blueprint $table) {
            $table->index('lab_id_fk', 'test_reference_range_lab_id_fk_idx');
            $table->index('age_unit_id_fk', 'test_reference_range_age_unit_id_fk_idx');
            $table->index(['test_id', 'gender_id_fk', 'age_unit_id_fk'], 'test_ref_range_lookup_idx');
        });

        // Test groups comment indexes
        Schema::table('test_groups_comment', function (Blueprint $table) {
            $table->index('test_group_id_fk', 'test_groups_comment_test_group_id_fk_idx');
            $table->index('lab_id_fk', 'test_groups_comment_lab_id_fk_idx');
        });

        // Test questions rel indexes
        Schema::table('test_questions_rel', function (Blueprint $table) {
            $table->index('lab_id_fk', 'test_questions_rel_lab_id_fk_idx');
            $table->index('test_id_fk', 'test_questions_rel_test_id_fk_idx');
            $table->index('question_id_fk', 'test_questions_rel_question_id_fk_idx');
        });

        // Packages indexes
        Schema::table('packages', function (Blueprint $table) {
            $table->index('lab_id_fk', 'packages_lab_id_fk_idx');
            $table->index(['deleted_at', 'lab_id_fk'], 'packages_deleted_lab_idx');
        });

        // Price list rels indexes
        Schema::table('price_list_rels', function (Blueprint $table) {
            $table->index('lab_id_fk');
            $table->index('price_list_id_fk');
            $table->index('test_id_fk');
            $table->index('test_group_id_fk', 'price_list_rels_test_group_id_fk_idx');
            $table->index('culture_id_fk', 'price_list_rels_culture_id_fk_idx');
            $table->index('package_id_fk', 'price_list_rels_package_id_fk_idx');
        });

        // Test package rel indexes
        Schema::table('test_package_rel', function (Blueprint $table) {
            $table->index('test_id_fk', 'test_package_rel_test_id_fk_idx');
            $table->index('package_id_fk', 'test_package_rel_package_id_fk_idx');
        });

        // Culture package rel indexes
        Schema::table('culture_package_rel', function (Blueprint $table) {
            $table->index('culture_id_fk', 'culture_package_rel_culture_id_fk_idx');
            $table->index('package_id_fk', 'culture_package_rel_package_id_fk_idx');
        });

        // Attributes indexes
        Schema::table('attributes', function (Blueprint $table) {
            $table->index('culture_id_fk', 'attributes_culture_id_fk_idx');
            $table->index('result_type_id_fk', 'attributes_result_type_id_fk_idx');
        });

        // Antibiotics indexes
        Schema::table('antibiotics', function (Blueprint $table) {
            $table->index('lab_id', 'antibiotics_lab_id_idx');
            $table->index(['deleted_at', 'lab_id'], 'antibiotics_deleted_lab_idx');
        });

        // Payment methods indexes
        Schema::table('payment_methods', function (Blueprint $table) {
            $table->index('lab_id_fk', 'payment_methods_lab_id_fk_idx');
            $table->index('contract_id_fk', 'payment_methods_contract_id_fk_idx');
            $table->index('invoice_id_fk', 'payment_methods_invoice_id_fk_idx');
        });

        // Discount types indexes
        Schema::table('discount_types', function (Blueprint $table) {
            $table->index('lab_id_fk', 'discount_types_lab_id_fk_idx');
            $table->index('contract_id_fk', 'discount_types_contract_id_fk_idx');
        });

        // Invoices indexes
        Schema::table('invoices', function (Blueprint $table) {
            $table->index('referral_id_fk');
            $table->index('created_at');
            $table->index('is_done');
            $table->index(['lab_id_fk', 'created_at']);
            $table->index(['patient_id_fk', 'created_at']);
            $table->index('from_lab_id_fk', 'invoices_from_lab_id_fk_idx');
            $table->index('sample_collector_id_fk', 'invoices_sample_collector_id_fk_idx');
            $table->index('contract_id_fk', 'invoices_contract_id_fk_idx');
            $table->index('signed_by_id_fk', 'invoices_signed_by_id_fk_idx');
            $table->index('discount_type_id_fk', 'invoices_discount_type_id_fk_idx');
            $table->index('barcode', 'invoices_barcode_idx');
            $table->index(['lab_id_fk', 'is_done', 'created_at'], 'invoices_lab_done_date_idx');
            $table->index(['deleted_at', 'lab_id_fk', 'created_at'], 'invoices_deleted_lab_date_idx');
        });

        // Invoice paid details indexes
        Schema::table('invoice_paid_details', function (Blueprint $table) {
            $table->index('lab_id_fk', 'invoice_paid_details_lab_id_fk_idx');
            $table->index('invoice_id_fk', 'invoice_paid_details_invoice_id_fk_idx');
            $table->index('payment_method_id_fk', 'invoice_paid_details_payment_method_id_fk_idx');
            $table->index('contract_id_fk', 'invoice_paid_details_contract_id_fk_idx');
        });

        // Invoice test rels indexes
        Schema::table('invoice_test_rels', function (Blueprint $table) {
            $table->index('culture_id_fk');
            $table->index('package_id_fk');
            $table->index('is_done');
            $table->index(['test_id_fk', 'is_done']);
            $table->index(['invoice_id_fk', 'is_done']);
            $table->index('test_group_id_fk', 'invoice_test_rels_test_group_id_fk_idx');
            $table->index('result_status_id_fk', 'invoice_test_rels_result_status_id_fk_idx');
            $table->index('to_lab_id_fk', 'invoice_test_rels_to_lab_id_fk_idx');
            $table->index('created_at', 'invoice_test_rels_created_at_idx');
            $table->index(['to_lab_id_fk', 'is_done', 'created_at'], 'invoice_test_rels_to_lab_done_date_idx');
        });

        // Bookings indexes
        Schema::table('bookings', function (Blueprint $table) {
            $table->index('patient_id_fk');
            $table->index('booking_date');
            $table->index(['lab_id_fk', 'booking_date']);
            $table->index(['deleted_at', 'lab_id_fk', 'booking_date'], 'bookings_deleted_lab_date_idx');
        });

        // Booking test rel indexes
        Schema::table('booking_test_rel', function (Blueprint $table) {
            $table->index('booking_id_fk', 'booking_test_rel_booking_id_fk_idx');
            $table->index('test_id_fk', 'booking_test_rel_test_id_fk_idx');
            $table->index('culture_id_fk', 'booking_test_rel_culture_id_fk_idx');
            $table->index('package_id_fk', 'booking_test_rel_package_id_fk_idx');
        });

        // Templates indexes
        Schema::table('templates', function (Blueprint $table) {
            $table->index('test_id_fk', 'templates_test_id_fk_idx');
            $table->index('culture_id_fk', 'templates_culture_id_fk_idx');
            $table->index('package_id_fk', 'templates_package_id_fk_idx');
            $table->index('test_group_id_fk', 'templates_test_group_id_fk_idx');
            $table->index('lab_id_fk', 'templates_lab_id_fk_idx');
            $table->index(['type', 'name'], 'templates_type_name_idx');
        });

        // Activity log indexes
        Schema::table('activity_log', function (Blueprint $table) {
            $table->index('parent_id', 'activity_log_parent_id_idx');
            $table->index(['creator_id', 'created_at'], 'activity_log_creator_date_idx');
            $table->index('event', 'activity_log_event_idx');
        });

        // Verify codes indexes
        Schema::table('verify_codes', function (Blueprint $table) {
            $table->index(['email', 'code'], 'verify_codes_email_code_idx');
            $table->index('email', 'verify_codes_email_idx');
            $table->index('created_at', 'verify_codes_created_at_idx');
        });

        // Rel labs referals indexes
        Schema::table('rel_labs_referals', function (Blueprint $table) {
            $table->index('referral_id_fk', 'rel_labs_referals_referral_id_fk_idx');
            $table->index('price_list_id_fk', 'rel_labs_referals_price_list_id_fk_idx');
            $table->index(['lab_id_fk', 'deleted_at'], 'rel_labs_referals_lab_active_idx');
        });

        // ============================================
        // POSTGRESQL-SPECIFIC PARTIAL INDEXES
        // ============================================
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('
                CREATE INDEX IF NOT EXISTS invoices_completed_lab_date_idx
                ON invoices (lab_id_fk, created_at DESC)
                WHERE is_done = true AND deleted_at IS NULL
            ');

            DB::statement('
                CREATE INDEX IF NOT EXISTS invoice_test_rels_completed_idx
                ON invoice_test_rels (invoice_id_fk)
                WHERE is_done = true
            ');

            DB::statement('
                CREATE INDEX IF NOT EXISTS patients_active_creator_idx
                ON patients (creator_id, created_at DESC)
                WHERE deleted_at IS NULL
            ');

            DB::statement('
                CREATE INDEX IF NOT EXISTS tests_content_gin_idx
                ON tests USING GIN (content jsonb_path_ops)
                WHERE content IS NOT NULL
            ');

            DB::statement('
                CREATE INDEX IF NOT EXISTS contracts_active_lab_idx
                ON contracts (lab_id_fk)
                WHERE deleted_at IS NULL
            ');
        }

        // Clear Spatie permission cache
        app('cache')
            ->store(config('permission.cache.store') != 'default' ? config('permission.cache.store') : null)
            ->forget(config('permission.cache.key'));
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop tables in reverse order to handle foreign key constraints
        Schema::dropIfExists('booking_test_rel');
        Schema::dropIfExists('bookings');
        Schema::dropIfExists('templates');
        Schema::dropIfExists('invoice_test_rels');
        Schema::dropIfExists('invoice_paid_details');
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('discount_types');
        Schema::dropIfExists('payment_methods');
        Schema::dropIfExists('antibiotics');
        Schema::dropIfExists('attributes');
        Schema::dropIfExists('culture_package_rel');
        Schema::dropIfExists('test_package_rel');
        Schema::dropIfExists('price_list_rels');
        Schema::dropIfExists('packages');
        Schema::dropIfExists('test_questions_rel');
        Schema::dropIfExists('test_groups_comment');
        Schema::dropIfExists('test_reference_range');
        Schema::dropIfExists('patient_questions');
        Schema::dropIfExists('tests');
        Schema::dropIfExists('cultures');
        Schema::dropIfExists('test_groups');
        Schema::dropIfExists('samples');
        Schema::dropIfExists('categories');
        Schema::dropIfExists('labs');
        Schema::dropIfExists('rel_labs_referals');
        Schema::dropIfExists('price_lists');
        Schema::dropIfExists('patients');
        Schema::dropIfExists('contracts');
        Schema::dropIfExists('result_statuses');
        Schema::dropIfExists('answer_types');
        Schema::dropIfExists('result_types');
        Schema::dropIfExists('duration_units');
        Schema::dropIfExists('age_units');
        Schema::dropIfExists('nationalities');
        Schema::dropIfExists('genders');
        Schema::dropIfExists('titles');
        Schema::dropIfExists('activity_log');
        Schema::dropIfExists('verify_codes');
        Schema::dropIfExists('personal_access_tokens');
        Schema::dropIfExists('failed_jobs');
        Schema::dropIfExists('job_batches');
        Schema::dropIfExists('jobs');
        Schema::dropIfExists('cache_locks');
        Schema::dropIfExists('cache');
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('role_has_permissions');
        Schema::dropIfExists('model_has_roles');
        Schema::dropIfExists('model_has_permissions');
        Schema::dropIfExists('roles');
        Schema::dropIfExists('permissions');
        Schema::dropIfExists('users');
    }
};
