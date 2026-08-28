<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| This app runs against the shared legacy `livedata` database, for which
| there are no migrations. Tests instead run on the sqlite `:memory:`
| connection (see phpunit.xml) with just the legacy-shaped tables they
| need, created fresh per test by `setUpLegacySchema()`. We deliberately
| do NOT use RefreshDatabase (there is nothing to migrate).
|
*/

pest()->extend(TestCase::class)
    ->beforeEach(fn () => setUpLegacySchema())
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Legacy schema (subset) for tests
|--------------------------------------------------------------------------
|
| Mirrors the shape of the real `users` and `roles` tables closely enough
| for auth, permission and profile tests. Extend this as more modules gain
| coverage — keep column names identical to `livedata`.
|
*/

function setUpLegacySchema(): void
{
    // HARD SAFETY GATE: this function drops and recreates tables. It must
    // only ever run against the throwaway in-memory sqlite connection. If a
    // cached config (bootstrap/cache/config.php) ever makes the default
    // connection point at MySQL/livedata, abort loudly instead of dropping
    // real tables.
    $connection = Schema::getConnection();
    if ($connection->getDriverName() !== 'sqlite' || $connection->getDatabaseName() !== ':memory:') {
        throw new RuntimeException(sprintf(
            'setUpLegacySchema() refused to run: expected sqlite :memory:, got [%s / %s]. '
            .'Clear the cached config (php artisan config:clear) and re-run.',
            $connection->getDriverName(),
            $connection->getDatabaseName(),
        ));
    }

    foreach ([
        'failed_payment_audit_logs', 'failed_payment_notes', 'failed_payment_communication_templates',
        'failed_payment_communications', 'failed_payment_webhook_events', 'failed_payment_retry_schedules',
        'failed_payment_status_histories', 'failed_payment_actions', 'failed_payment_attempts',
        'failed_payment_settings', 'failed_payment_cases',
        'payment_health_events', 'payment_health_snapshots', 'offer_notes',
        'client_cancel_infos', 'cancel_script_options', 'cancel_scripts',
        'client_renew_statuses', 'client_statuses', 'client_renew_track_actions',
        'client_track_actions', 'client_renew_button_tracks', 'client_button_tracks',
        'renew_agrement_pdfs', 'renewal_clients',
        'plan_change_logs', 'custom_plan_prices',
        'card_profiles', 'payment_links', 'bonuses',
        'anet_webhook_event_reads', 'anet_webhook_events', 'authorize_accounts',
        'claim_webhook_logs', 'tech_ratings', 'claim_tracks', 'claim_files',
        'claim_closed_details', 'parts', 'issues', 'systems', 'brands',
        'technicians', 'sale_commissions', 'client_earnings', 'salesman_activities',
        'track_salesman_actions', 'converted_lead_statuses', 'converted_leads',
        'email_signatures', 'sms_signatures', 'voice_signatures',
        'entrances', 'webhooks', 'sms_texts', 'constant_contacts',
        'buy_numbers', 'track_buttons',
        'lead_blast_items', 'lead_blast_runs', 'lead_ai_scores', 'dnc_numbers',
        'salesman_messages', 'entrance_auths', 'entrance_grp_messages',
        'clone_entrances', 'basic_settings',
        'analytics_customers', 'analytics_sales_events', 'analytics_payments',
        'analytics_claims', 'analytics_leads', 'lead_automation_logs', 'billing_settings',
        'blogs', 'banners', 'buyer_sellers', 'partners', 'contact_us',
        'promotions', 'promotion_conditions', 'inquiries', 'wl_inqueries',
        'temp_data', 'zip_codes', 'addons', 'property_sizes', 'property_types',
        'client_login_infos', 'agent_orders', 'agents', 'companies',
        'reminders', 'lead_details', 'campaign_files', 'leads_status', 'claims',
        'packages', 'notes', 'payment_histories', 'clients', 'users', 'roles',
        'password_resets',
    ] as $t) {
        Schema::dropIfExists($t);
    }

    Schema::create('password_resets', function (Blueprint $table) {
        $table->string('email')->index();
        $table->string('token');
        $table->timestamp('created_at')->nullable();
    });

    Schema::create('roles', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('user_id')->nullable();
        $table->string('name');
        $table->text('permissions')->nullable();
        $table->timestamps();
    });

    Schema::create('users', function (Blueprint $table) {
        $table->id();
        $table->string('role_id')->nullable();
        $table->string('first_name')->nullable();
        $table->string('last_name')->nullable();
        $table->string('email')->nullable()->unique();
        $table->timestamp('email_verified_at')->nullable();
        $table->string('password')->nullable();
        $table->string('contact')->nullable();
        $table->text('address')->nullable();
        $table->string('city')->nullable();
        $table->string('state')->nullable();
        $table->string('zip_code')->nullable();
        $table->string('picture')->nullable();
        $table->string('ext_number')->nullable();
        $table->string('v_salesman_id')->nullable();
        $table->integer('type')->default(0);
        $table->boolean('login_off')->default(false);
        $table->string('remember_token', 100)->nullable();
        $table->timestamp('last_activity')->nullable();
        $table->integer('attempts')->default(0);
        $table->tinyInteger('status')->default(0);
        $table->timestamps();
        $table->softDeletes();
    });

    Schema::create('clients', function (Blueprint $table) {
        $table->id();
        $table->string('policy_number')->nullable();
        $table->string('password')->nullable();
        $table->string('first_name')->nullable();
        $table->string('last_name')->nullable();
        $table->string('email')->nullable();
        $table->string('contact')->nullable();
        $table->string('promo_code')->nullable();
        $table->text('address')->nullable();
        $table->string('city')->nullable();
        $table->string('state')->nullable();
        $table->string('zip_code')->nullable();
        $table->string('payment_structure')->nullable();
        $table->integer('payment_type')->default(0);
        $table->string('installment')->nullable();
        $table->integer('paid_installment')->default(0);
        $table->text('bank_info')->nullable();
        $table->unsignedBigInteger('package')->nullable();
        $table->unsignedBigInteger('property_type')->nullable();
        $table->unsignedBigInteger('property_size')->nullable();
        $table->string('discount')->nullable();
        $table->integer('bypass_payment')->default(0);
        $table->string('salesman_name')->nullable();
        $table->string('start_date')->nullable();
        $table->string('end_date')->nullable();
        $table->text('services')->nullable();
        $table->string('call_fee')->nullable();
        $table->text('description')->nullable();
        $table->string('custom_s_date')->nullable();
        $table->string('custom_e_date')->nullable();
        $table->string('custom_purchaes_date')->nullable();
        $table->timestamp('plan_changed_at')->nullable();
        $table->integer('status')->default(1);
        $table->boolean('login_off')->default(false);
        $table->boolean('star')->default(false);
        $table->text('ip_info')->nullable();
        $table->timestamp('last_login_at')->nullable();
        $table->string('signature_date_time')->nullable();
        $table->integer('is_renew')->default(0);
        $table->boolean('renewed')->default(0);
        $table->integer('agent_id')->nullable();
        $table->string('multi_property')->nullable();
        $table->string('disputed')->nullable();
        $table->string('client_type')->nullable();
        $table->string('authorize_status')->nullable();
        $table->integer('authorize_account_id')->nullable();
        $table->timestamp('payment_faild_date')->nullable();
        $table->text('price_cal_response')->nullable();
        $table->string('custom_price')->nullable();
        $table->string('cancle_date')->nullable();
        $table->string('refund_date')->nullable();
        $table->timestamps();
        $table->softDeletes();
    });

    Schema::create('payment_histories', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('client_id')->default(0);
        $table->string('plan_id')->nullable();
        $table->string('purpose')->nullable();
        $table->string('card_number')->nullable();
        $table->string('expMonth')->nullable();
        $table->string('expYear')->nullable();
        $table->float('amount')->default(0);
        $table->string('currency')->default('$');
        $table->string('payment_date')->nullable();
        $table->string('payment_s_date')->nullable();
        $table->integer('status')->default(0);
        $table->tinyInteger('payment_status')->default(0);
        $table->tinyInteger('claim_payment')->default(0);
        $table->integer('claim_id')->nullable();
        $table->integer('payment_type')->default(0);
        $table->tinyInteger('instalment')->default(0);
        $table->string('subscription_id')->nullable();
        $table->string('transaction_id')->nullable();
        $table->string('refund_transaction_id')->nullable();
        $table->boolean('cancle_subscription')->default(false);
        $table->float('refund_amount')->nullable();
        $table->integer('refund_claim')->default(0);
        $table->integer('refund_payment')->default(0);
        $table->string('refund_date')->nullable();
        $table->tinyInteger('disputed')->default(0);
        $table->tinyInteger('win')->default(0);
        $table->tinyInteger('loss')->default(0);
        $table->tinyInteger('is_custom')->default(0);
        $table->text('bank_info')->nullable();
        $table->string('color')->nullable();
        $table->integer('authorize_account_id')->nullable();
        $table->timestamps();
    });

    Schema::create('authorize_accounts', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->string('environment')->default('production');
        $table->text('api_login_id');
        $table->text('transaction_key');
        $table->text('public_client_key')->nullable();
        $table->text('signature_key')->nullable();
        $table->boolean('is_active')->default(true);
        $table->boolean('is_disabled')->default(false);
        $table->boolean('is_default_charge')->default(false);
        $table->boolean('is_default_client')->default(false);
        $table->unsignedInteger('weight')->default(100);
        $table->text('notes')->nullable();
        $table->timestamps();
    });

    Schema::create('anet_webhook_events', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('authorize_account_id')->nullable();
        $table->string('event_id')->unique();
        $table->string('event_type')->nullable();
        $table->string('payload_id')->nullable();
        $table->longText('raw')->nullable();
        $table->timestamps();
    });

    Schema::create('anet_webhook_event_reads', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('anet_webhook_event_id');
        $table->unsignedBigInteger('admin_id');
        $table->timestamp('read_at')->nullable();
        $table->timestamps();
    });

    Schema::create('notes', function (Blueprint $table) {
        $table->increments('id');
        $table->integer('lead_id')->nullable();
        $table->string('user_id')->nullable();
        $table->string('client_id')->nullable();
        $table->text('notes');
        $table->string('date_time');
        $table->tinyInteger('status')->nullable();
        $table->timestamps();
    });

    Schema::create('packages', function (Blueprint $table) {
        $table->id();
        $table->string('title')->nullable();
        $table->string('yearly')->default(0);
        $table->string('monthly')->default(0);
        $table->string('currency')->default('$');
        $table->text('detail')->nullable();
        $table->timestamps();
        $table->softDeletes();
    });

    foreach (['addons', 'property_sizes', 'property_types'] as $catalog) {
        Schema::create($catalog, function (Blueprint $table) use ($catalog) {
            $table->id();
            $table->string('title')->nullable();
            if ($catalog === 'addons') {
                $table->string('subtitle')->nullable();
            }
            if ($catalog === 'property_types') {
                $table->string('image')->nullable();
            }
            $table->string('yearly')->default(0);
            $table->string('monthly')->default(0);
            $table->string('currency')->default('$');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    Schema::create('package_services_list', function (Blueprint $table) {
        $table->id();
        $table->string('title')->nullable();
        $table->timestamps();
        $table->softDeletes();
    });

    Schema::create('package_services', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('package_id');
        $table->unsignedBigInteger('package_service_id');
        $table->timestamps();
        $table->softDeletes();
    });

    Schema::create('promotions', function (Blueprint $table) {
        $table->increments('id');
        $table->string('name');
        $table->float('amount');
        $table->integer('promotion_type')->nullable();
        $table->timestamps();
    });

    Schema::create('promotion_conditions', function (Blueprint $table) {
        $table->increments('id');
        $table->integer('promotion_id');
        $table->string('name')->nullable();
        $table->string('package_id')->nullable();
        $table->timestamps();
    });

    Schema::create('inquiries', function (Blueprint $table) {
        $table->id();
        $table->integer('lead_id')->nullable();
        $table->string('first_name')->nullable();
        $table->string('last_name')->nullable();
        $table->string('contact')->nullable();
        $table->string('email')->nullable();
        $table->unsignedBigInteger('status')->default(0);
        $table->string('shop')->nullable();
        $table->integer('zip_code')->nullable();
        $table->string('state')->nullable();
        $table->string('city')->nullable();
        $table->string('address')->nullable();
        $table->tinyInteger('property_size')->nullable();
        $table->tinyInteger('property_type')->nullable();
        $table->integer('package_id')->nullable();
        $table->integer('progress_bar')->nullable();
        $table->text('addons')->nullable();
        $table->timestamps();
        $table->softDeletes();
    });

    Schema::create('wl_inqueries', function (Blueprint $table) {
        $table->increments('id');
        $table->string('first_name')->nullable();
        $table->string('last_name')->nullable();
        $table->integer('zip_code')->nullable();
        $table->string('email')->nullable();
        $table->integer('step')->nullable();
        $table->string('contact')->nullable();
        $table->integer('package_id')->nullable();
        $table->integer('property_size')->nullable();
        $table->integer('property_type')->nullable();
        $table->string('addons')->nullable();
        $table->string('address')->nullable();
        $table->string('promo_name')->nullable();
        $table->integer('payment_term')->nullable();
        $table->timestamps();
    });

    Schema::create('temp_data', function (Blueprint $table) {
        $table->increments('id');
        $table->string('first_name');
        $table->string('last_name');
        $table->string('email');
        $table->string('contact');
        $table->integer('zip_code')->nullable();
        $table->integer('campaign_id');
        $table->integer('serial_number')->nullable();
        $table->timestamps();
    });

    Schema::create('companies', function (Blueprint $table) {
        $table->increments('id');
        $table->string('name');
        $table->string('image')->nullable();
        $table->string('type');
        $table->boolean('status')->default(true);
        $table->timestamps();
    });

    Schema::create('agents', function (Blueprint $table) {
        $table->increments('id');
        $table->integer('company_id');
        $table->integer('role')->nullable();
        $table->string('name')->nullable();
        $table->string('image')->nullable();
        $table->string('email');
        $table->string('second_email')->nullable();
        $table->string('password');
        $table->string('confirm_password')->nullable();
        $table->string('address_one')->nullable();
        $table->string('address_two')->nullable();
        $table->string('city')->nullable();
        $table->string('state')->nullable();
        $table->integer('postal_code')->nullable();
        $table->bigInteger('office_number')->nullable();
        $table->bigInteger('mobile_number')->nullable();
        $table->timestamps();
    });

    Schema::create('agent_orders', function (Blueprint $table) {
        $table->increments('id');
        $table->integer('agent_id');
        $table->string('plan_name')->nullable();
        $table->string('address_one')->nullable();
        $table->string('address_two')->nullable();
        $table->string('name')->nullable();
        $table->string('email')->nullable();
        $table->string('phone')->nullable();
        $table->longText('stepproperty')->nullable();
        $table->longText('stepbuyer')->nullable();
        $table->longText('addondata')->nullable();
        $table->longText('closingInfo')->nullable();
        $table->longText('buyerAgent')->nullable();
        $table->longText('sellerAgent')->nullable();
        $table->timestamps();
    });

    Schema::create('client_login_infos', function (Blueprint $table) {
        $table->increments('id');
        $table->integer('client_id');
        $table->text('ip_info')->nullable();
        $table->timestamp('last_login_at')->nullable();
        $table->timestamps();
    });

    Schema::create('zip_codes', function (Blueprint $table) {
        $table->id();
        $table->string('first_name')->nullable();
        $table->string('last_name')->nullable();
        $table->string('email')->nullable();
        $table->string('contact')->nullable();
        $table->string('address')->nullable();
        $table->string('state')->nullable();
        $table->string('city')->nullable();
        $table->string('zip_code')->nullable();
        $table->unsignedBigInteger('status')->default(0);
        $table->timestamps();
    });

    Schema::create('claims', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('client_id')->default(0);
        $table->string('claim_no')->default(0);
        $table->unsignedBigInteger('system')->default(0);
        $table->text('issue')->nullable();
        $table->string('other_issue')->nullable();
        $table->unsignedBigInteger('brand')->default(0);
        $table->string('serial_no')->nullable();
        $table->string('location')->nullable();
        $table->string('model_no')->nullable();
        $table->text('last_time_working_detail')->nullable();
        $table->text('problem')->nullable();
        $table->integer('status')->default(0);
        $table->integer('serial_number')->default(0);
        $table->string('claim_color')->nullable();
        $table->integer('tech_id')->nullable();
        $table->string('tech_schedule')->nullable();
        $table->integer('claim_status')->default(0);
        $table->float('owed_price')->default(0);
        $table->float('total_price')->default(0);
        $table->boolean('tech_paid_status')->default(false);
        $table->boolean('is_callfee')->default(false);
        $table->string('authorize_status')->nullable();
        $table->json('replit_claim_payload')->nullable();
        $table->string('replit_event')->nullable();
        $table->string('replit_workflow_step')->nullable();
        $table->string('replit_status')->nullable();
        $table->string('replit_sub_status')->nullable();
        $table->timestamp('replit_payload_received_at')->nullable();
        $table->timestamps();
        $table->softDeletes();
    });

    Schema::create('technicians', function (Blueprint $table) {
        $table->id();
        $table->string('first_name')->nullable();
        $table->string('last_name')->nullable();
        $table->string('business_name')->nullable();
        $table->string('contact')->nullable();
        $table->string('email')->nullable();
        $table->text('address')->nullable();
        $table->json('city')->nullable();
        $table->json('state')->nullable();
        $table->string('zip_code')->nullable();
        $table->string('services')->default('[]');
        $table->string('notes')->nullable();
        $table->float('call_fee')->nullable();
        $table->boolean('in_ex')->default(false);
        $table->timestamps();
        $table->softDeletes();
    });

    Schema::create('systems', function (Blueprint $table) {
        $table->id();
        $table->string('title')->nullable();
        $table->integer('brand')->default(0);
        $table->string('brand_title')->default('[]');
        $table->timestamps();
        $table->softDeletes();
    });

    Schema::create('brands', function (Blueprint $table) {
        $table->id();
        $table->string('title')->nullable();
        $table->longText('testdata')->nullable();
        $table->timestamps();
        $table->softDeletes();
    });

    Schema::create('issues', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('system_id')->default(0);
        $table->text('title')->nullable();
        $table->text('delete_reason')->nullable();
        $table->timestamps();
        $table->softDeletes();
    });

    Schema::create('parts', function (Blueprint $table) {
        $table->increments('id');
        $table->integer('claim_id');
        $table->string('name')->nullable();
        $table->float('price')->nullable();
        $table->string('model')->nullable();
        $table->string('purchaes_form')->nullable();
        $table->string('order_number')->nullable();
        $table->string('tracking_number')->nullable();
        $table->float('labor_price')->nullable();
        $table->boolean('who_buy')->default(true);
        $table->string('es_arrival')->nullable();
        $table->timestamps();
    });

    Schema::create('claim_closed_details', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('claim_id')->default(0);
        $table->unsignedBigInteger('technician_id')->default(0);
        $table->string('dispatch_amount')->default(0);
        $table->string('part_name')->nullable();
        $table->string('part_cost')->default(0);
        $table->string('currency')->default('$');
        $table->text('notes')->nullable();
        $table->float('claim_spent_price')->nullable();
        $table->timestamps();
        $table->softDeletes();
    });

    Schema::create('claim_files', function (Blueprint $table) {
        $table->increments('id');
        $table->integer('claim_id');
        $table->text('upload_file');
        $table->timestamps();
    });

    Schema::create('claim_tracks', function (Blueprint $table) {
        $table->increments('id');
        $table->integer('claim_id');
        $table->integer('user_id')->nullable();
        $table->string('create_date')->nullable();
        $table->string('duration')->nullable();
        $table->integer('status')->nullable();
        $table->text('button_text')->nullable();
        $table->longText('comments')->nullable();
        $table->string('who_view')->nullable();
        $table->timestamps();
    });

    Schema::create('tech_ratings', function (Blueprint $table) {
        $table->increments('id');
        $table->integer('client_id');
        $table->integer('claim_id');
        $table->integer('tech_id');
        $table->integer('stars');
        $table->string('notes');
        $table->timestamps();
    });

    Schema::create('claim_webhook_logs', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('claim_id')->nullable();
        $table->string('claim_no')->nullable();
        $table->string('source')->nullable();
        $table->string('event')->nullable();
        $table->string('workflow_step')->nullable();
        $table->string('status')->nullable();
        $table->string('sub_status')->nullable();
        $table->json('payload')->nullable();
        $table->timestamp('payload_timestamp')->nullable();
        $table->timestamp('step_entered_at')->nullable();
        $table->timestamp('received_at')->nullable();
        $table->boolean('matched_claim')->default(false);
        $table->text('note')->nullable();
        $table->timestamps();
    });

    Schema::create('card_profiles', function (Blueprint $table) {
        $table->increments('id');
        $table->integer('payment_histroy_id')->nullable();
        $table->integer('client_id')->nullable();
        $table->integer('authorize_account_id')->nullable();
        $table->string('customer_profile_id')->nullable();
        $table->string('payment_profile_id')->nullable();
        $table->string('last4')->nullable();
        $table->string('brand')->nullable();
        $table->string('expMonth')->nullable();
        $table->string('expYear')->nullable();
        $table->string('routingNumber')->nullable();
        $table->string('accountNumber')->nullable();
        $table->string('bankAccountName')->nullable();
        $table->string('echeckType')->nullable();
        $table->boolean('is_default')->default(false);
        $table->timestamps();
    });

    Schema::create('payment_links', function (Blueprint $table) {
        $table->id();
        $table->integer('claim_id')->nullable();
        $table->integer('client_id')->nullable();
        $table->text('link')->nullable();
        $table->string('type')->nullable();
        $table->text('token')->nullable();
        $table->string('status')->default('1');
        $table->timestamps();
        $table->softDeletes();
    });

    Schema::create('bonuses', function (Blueprint $table) {
        $table->increments('id');
        $table->integer('bonus_one')->nullable();
        $table->float('bonus_one_price')->nullable();
        $table->integer('bonus_two')->nullable();
        $table->float('bonus_two_price')->nullable();
        $table->boolean('commission_double')->default(false);
        $table->timestamps();
    });

    Schema::create('plan_change_logs', function (Blueprint $table) {
        $table->id();
        $table->integer('client_id');
        $table->integer('changed_by_user_id')->nullable();
        $table->integer('old_package_id')->nullable();
        $table->tinyInteger('old_payment_structure')->nullable();
        $table->tinyInteger('old_installment')->nullable();
        $table->integer('old_term_months')->nullable();
        $table->integer('new_term_months')->nullable();
        $table->integer('new_package_id')->nullable();
        $table->tinyInteger('new_payment_structure')->nullable();
        $table->tinyInteger('new_installment_type')->nullable();
        $table->decimal('amount_already_paid', 10, 2)->nullable();
        $table->decimal('new_total_price', 10, 2)->nullable();
        $table->decimal('remaining_or_credit', 10, 2)->nullable();
        $table->decimal('immediate_charge_amount', 10, 2)->nullable();
        $table->decimal('new_installment_amount', 10, 2)->nullable();
        $table->integer('new_installment_occurrences')->nullable();
        $table->string('old_subscription_id', 64)->nullable();
        $table->string('old_cancel_result', 32)->nullable();
        $table->string('new_subscription_id', 64)->nullable();
        $table->date('new_arb_start_date')->nullable();
        $table->decimal('overpaid_amount', 10, 2)->nullable();
        $table->decimal('surplus_amount', 10, 2)->nullable();
        $table->integer('surplus_skip_months')->nullable();
        $table->string('status', 32)->default('pending');
        $table->string('failure_stage', 50)->nullable();
        $table->text('error_message')->nullable();
        $table->timestamps();
    });

    Schema::create('custom_plan_prices', function (Blueprint $table) {
        $table->increments('id');
        $table->integer('package_id');
        $table->float('original_yearly');
        $table->float('original_monthly');
        $table->float('new_yearly');
        $table->float('new_monthly');
        $table->timestamps();
    });

    Schema::create('renewal_clients', function (Blueprint $table) {
        $table->increments('id');
        $table->bigInteger('client_id');
        $table->timestamps();
    });

    Schema::create('renew_agrement_pdfs', function (Blueprint $table) {
        $table->increments('id');
        $table->integer('client_id');
        $table->text('signature')->nullable();
        $table->string('initial_pdf')->nullable();
        $table->string('updated_pdf')->nullable();
        $table->string('multi_agreement_pdf')->nullable();
        $table->string('vip_pdf')->nullable();
        $table->string('signature_date_time')->nullable();
        $table->string('choose_pdf')->nullable();
        $table->timestamps();
    });

    foreach (['client_button_tracks', 'client_renew_button_tracks'] as $bt) {
        Schema::create($bt, function (Blueprint $table) {
            $table->increments('id');
            $table->integer('client_id');
            $table->integer('user_id')->nullable();
            $table->string('create_date')->nullable();
            $table->string('duration')->nullable();
            $table->integer('status')->nullable();
            $table->string('button_text')->nullable();
            $table->longText('comments')->nullable();
            $table->timestamps();
        });
    }

    foreach (['client_track_actions', 'client_renew_track_actions'] as $ta) {
        Schema::create($ta, function (Blueprint $table) {
            $table->increments('id');
            $table->bigInteger('client_id');
            $table->integer('user_id');
            $table->string('click_to_call')->nullable();
            $table->string('save')->nullable();
            $table->string('send_sms')->nullable();
            $table->string('send_mail')->nullable();
            $table->string('voice')->nullable();
            $table->string('convert_to_client')->nullable();
            $table->string('client_status')->nullable();
            $table->timestamp('STORE_DATE')->nullable();
            $table->timestamps();
        });
    }

    foreach (['client_statuses', 'client_renew_statuses'] as $cs) {
        Schema::create($cs, function (Blueprint $table) {
            $table->increments('id');
            $table->string('title')->nullable();
            $table->timestamps();
        });
    }

    Schema::create('cancel_scripts', function (Blueprint $table) {
        $table->increments('id');
        $table->string('title');
        $table->longText('script_text');
        $table->timestamps();
    });

    Schema::create('cancel_script_options', function (Blueprint $table) {
        $table->increments('id');
        $table->integer('cancel_script_id');
        $table->string('title');
        $table->timestamps();
    });

    Schema::create('client_cancel_infos', function (Blueprint $table) {
        $table->increments('id');
        $table->integer('client_id');
        $table->integer('script_id');
        $table->integer('cancel_script_option_id')->nullable();
        $table->string('rep_notes')->nullable();
        $table->timestamp('last_selected_at')->nullable();
        $table->timestamps();
    });

    Schema::create('offer_notes', function (Blueprint $table) {
        $table->increments('id');
        $table->integer('user_id');
        $table->integer('client_id');
        $table->string('option_title')->nullable();
        $table->text('notes')->nullable();
        $table->string('outcome')->nullable();
        $table->string('start_date')->nullable();
        $table->string('end_date')->nullable();
        $table->timestamps();
    });

    Schema::create('payment_health_snapshots', function (Blueprint $table) {
        $table->id();
        $table->date('report_month');
        $table->unsignedInteger('paid_count')->default(0);
        $table->unsignedInteger('failed_count')->default(0);
        $table->unsignedInteger('refund_count')->default(0);
        $table->unsignedInteger('dispute_count')->default(0);
        $table->unsignedInteger('renewal_count')->default(0);
        $table->decimal('gross_amount', 12, 2)->default(0);
        $table->decimal('failed_amount', 12, 2)->default(0);
        $table->decimal('refund_amount', 12, 2)->default(0);
        $table->decimal('dispute_amount', 12, 2)->default(0);
        $table->decimal('net_amount', 12, 2)->default(0);
        $table->text('notes')->nullable();
        $table->timestamps();
    });

    Schema::create('payment_health_events', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('client_id')->nullable();
        $table->unsignedBigInteger('payment_history_id')->nullable();
        $table->unsignedBigInteger('authorize_account_id')->nullable();
        $table->string('event_type', 120);
        $table->string('status', 50)->default('open');
        $table->string('title', 190);
        $table->text('description')->nullable();
        $table->json('payload')->nullable();
        $table->timestamp('resolved_at')->nullable();
        $table->timestamps();
    });

    // ---- Failed Payment Module (Phase 8) ----
    Schema::create('failed_payment_cases', function (Blueprint $table) {
        $table->id();
        $table->uuid('case_uuid')->unique();
        $table->string('case_key', 64)->unique();
        $table->unsignedBigInteger('client_id')->nullable();
        $table->unsignedBigInteger('payment_history_id')->nullable();
        $table->unsignedBigInteger('authorize_account_id')->nullable();
        $table->string('anet_webhook_event_id')->nullable();
        $table->unsignedBigInteger('claim_id')->nullable();
        $table->string('arb_subscription_id')->nullable();
        $table->string('anet_transaction_id')->nullable();
        $table->date('scheduled_payment_date')->nullable();
        $table->decimal('failed_amount', 12, 2)->nullable();
        $table->string('currency', 16)->nullable();
        $table->string('failure_code', 120)->nullable();
        $table->text('failure_reason')->nullable();
        $table->string('failure_category', 120)->nullable();
        $table->string('failure_source', 120)->nullable();
        $table->string('payment_frequency', 50)->nullable();
        $table->timestamp('first_failed_at')->nullable();
        $table->timestamp('latest_failed_at')->nullable();
        $table->string('current_status', 50)->default('new');
        $table->unsignedInteger('attempt_count')->default(0);
        $table->timestamp('next_action_at')->nullable();
        $table->timestamp('recovered_at')->nullable();
        $table->timestamp('resolved_at')->nullable();
        $table->unsignedBigInteger('assigned_user_id')->nullable();
        $table->string('priority', 20)->default('normal');
        $table->json('metadata')->nullable();
        $table->timestamps();
    });

    Schema::create('failed_payment_attempts', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('failed_payment_case_id');
        $table->string('anet_webhook_event_id')->nullable();
        $table->unsignedBigInteger('payment_history_id')->nullable();
        $table->unsignedSmallInteger('attempt_number');
        $table->string('attempt_type', 50);
        $table->string('attempt_source', 50);
        $table->timestamp('scheduled_at')->nullable();
        $table->timestamp('started_at')->nullable();
        $table->timestamp('completed_at')->nullable();
        $table->decimal('amount', 12, 2)->nullable();
        $table->string('status', 50);
        $table->string('gateway_response_code', 50)->nullable();
        $table->text('gateway_response_message')->nullable();
        $table->string('anet_transaction_id', 120)->nullable();
        $table->string('idempotency_key', 64)->unique();
        $table->boolean('is_automatic')->default(false);
        $table->json('metadata')->nullable();
        $table->timestamps();
    });

    Schema::create('failed_payment_actions', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('failed_payment_case_id');
        $table->unsignedBigInteger('failed_payment_attempt_id')->nullable();
        $table->string('action_type', 50);
        $table->string('action_source', 50);
        $table->string('status', 50);
        $table->timestamp('scheduled_at')->nullable();
        $table->timestamp('performed_at')->nullable();
        $table->unsignedBigInteger('performed_by_user_id')->nullable();
        $table->string('recipient')->nullable();
        $table->string('template')->nullable();
        $table->string('provider_reference')->nullable();
        $table->text('failure_reason')->nullable();
        $table->json('metadata')->nullable();
        $table->timestamps();
    });

    Schema::create('failed_payment_status_histories', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('failed_payment_case_id');
        $table->string('previous_status', 50)->nullable();
        $table->string('new_status', 50);
        $table->text('change_reason')->nullable();
        $table->string('changed_by_type', 50);
        $table->unsignedBigInteger('changed_by_user_id')->nullable();
        $table->json('metadata')->nullable();
        $table->timestamp('created_at')->useCurrent();
        $table->timestamp('updated_at')->nullable();
    });

    Schema::create('failed_payment_retry_schedules', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('failed_payment_case_id');
        $table->unsignedSmallInteger('sequence_number');
        $table->unsignedSmallInteger('retry_delay_days');
        $table->timestamp('scheduled_at')->nullable();
        $table->string('status', 50)->default('scheduled');
        $table->timestamp('locked_at')->nullable();
        $table->timestamp('processing_started_at')->nullable();
        $table->timestamp('completed_at')->nullable();
        $table->unsignedBigInteger('failed_payment_attempt_id')->nullable();
        $table->text('cancellation_reason')->nullable();
        $table->json('metadata')->nullable();
        $table->timestamps();
    });

    Schema::create('failed_payment_webhook_events', function (Blueprint $table) {
        $table->id();
        $table->string('provider', 50);
        $table->string('event_id', 191)->nullable();
        $table->string('event_type', 191)->nullable();
        $table->char('payload_hash', 64);
        $table->json('sanitized_payload')->nullable();
        $table->string('signature_validation_status', 50);
        $table->timestamp('received_at')->nullable();
        $table->timestamp('processed_at')->nullable();
        $table->string('processing_status', 50)->default('received');
        $table->text('error_details')->nullable();
        $table->unsignedBigInteger('failed_payment_case_id')->nullable();
        $table->json('metadata')->nullable();
        $table->timestamps();
    });

    Schema::create('failed_payment_communications', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('failed_payment_case_id');
        $table->string('channel', 50);
        $table->string('recipient')->nullable();
        $table->string('template_name', 120)->nullable();
        $table->string('subject')->nullable();
        $table->longText('sanitized_message')->nullable();
        $table->string('provider_reference')->nullable();
        $table->string('status', 50)->default('queued');
        $table->timestamp('queued_at')->nullable();
        $table->timestamp('sent_at')->nullable();
        $table->timestamp('delivered_at')->nullable();
        $table->timestamp('failed_at')->nullable();
        $table->text('failure_reason')->nullable();
        $table->string('initiated_by', 120)->nullable();
        $table->json('metadata')->nullable();
        $table->timestamps();
    });

    Schema::create('failed_payment_communication_templates', function (Blueprint $table) {
        $table->id();
        $table->string('template_key', 120)->unique();
        $table->string('label', 160);
        $table->unsignedInteger('version')->default(1);
        $table->unsignedInteger('sort_order')->default(0);
        $table->json('channels');
        $table->string('subject_template', 255)->nullable();
        $table->longText('body_template')->nullable();
        $table->boolean('active')->default(true);
        $table->timestamps();
    });

    Schema::create('failed_payment_notes', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('failed_payment_case_id');
        $table->longText('note_body');
        $table->string('note_type', 50)->default('internal');
        $table->unsignedBigInteger('created_by')->nullable();
        $table->json('metadata')->nullable();
        $table->timestamps();
    });

    Schema::create('failed_payment_audit_logs', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('failed_payment_case_id')->nullable();
        $table->string('previous_status', 50)->nullable();
        $table->string('new_status', 50)->nullable();
        $table->string('action', 120);
        $table->string('source', 120)->nullable();
        $table->unsignedBigInteger('admin_id')->nullable();
        $table->string('ip_address', 45)->nullable();
        $table->json('safe_request_metadata')->nullable();
        $table->text('notes')->nullable();
        $table->json('metadata')->nullable();
        $table->timestamps();
    });

    Schema::create('failed_payment_settings', function (Blueprint $table) {
        $table->id();
        $table->string('setting_key', 191)->unique();
        $table->json('setting_value')->nullable();
        $table->boolean('is_active')->default(true);
        $table->string('updated_by', 120)->nullable();
        $table->timestamps();
    });

    Schema::create('leads_status', function (Blueprint $table) {
        $table->increments('id');
        $table->string('title')->nullable();
        $table->timestamps();
    });

    Schema::create('campaign_files', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('user_id')->default(0);
        $table->string('name')->nullable();
        $table->integer('status')->default(1);
        $table->string('price_type')->nullable();
        $table->string('camp_price')->nullable();
        $table->string('camp_price_two')->nullable();
        $table->string('camp_per_lead')->nullable();
        $table->string('camp_per_camp')->nullable();
        $table->timestamps();
        $table->softDeletes();
    });

    Schema::create('lead_details', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('campaign_id')->default(0);
        $table->string('user_id')->nullable();
        $table->string('policy_number')->default(0);
        $table->string('first_name')->nullable();
        $table->string('last_name')->nullable();
        $table->string('contact')->nullable();
        $table->string('email')->nullable();
        $table->text('address')->nullable();
        $table->string('city')->nullable();
        $table->string('state')->nullable();
        $table->string('zip_code')->nullable();
        $table->text('notes')->nullable();
        $table->unsignedBigInteger('status')->default(0);
        $table->unsignedBigInteger('reminder_id')->default(0);
        $table->string('reminder_status')->default(0);
        $table->unsignedBigInteger('property_size')->default(0);
        $table->unsignedBigInteger('property_type')->default(0);
        $table->unsignedBigInteger('package')->default(0);
        $table->unsignedBigInteger('services_call_fee')->default(0);
        $table->unsignedBigInteger('live_charges')->default(0);
        $table->text('additional_comments')->nullable();
        $table->unsignedBigInteger('apply_discounts')->default(0);
        $table->unsignedBigInteger('payment_terms')->default(0);
        $table->unsignedBigInteger('instalments')->default(0);
        $table->bigInteger('sale_man')->nullable();
        $table->string('longitude')->nullable();
        $table->string('latitude')->nullable();
        $table->string('email_date_time')->nullable();
        $table->tinyInteger('mail_type')->nullable();
        $table->string('contact_text')->nullable();
        $table->string('phone_company')->nullable();
        $table->text('message')->nullable();
        $table->boolean('color_change')->default(false);
        $table->boolean('is_download')->default(false);
        $table->integer('sms_mail')->nullable();
        $table->integer('mail')->nullable();
        $table->integer('not_pull')->nullable();
        $table->integer('serial_number')->nullable();
        $table->integer('priority_indx')->nullable();
        $table->integer('priority_checker')->nullable();
        $table->integer('show_status')->nullable();
        $table->integer('progress_bar')->nullable();
        $table->string('modernize_id')->nullable();
        $table->text('addons')->nullable();
        $table->string('lead_tag')->nullable();
        $table->string('lead_type')->nullable();
        $table->string('lock_lead')->nullable();
        $table->timestamp('lock_time')->nullable();
        $table->timestamp('msg_date')->nullable();
        $table->timestamps();
        $table->softDeletes();
    });

    Schema::create('reminders', function (Blueprint $table) {
        $table->id();
        $table->string('user_id')->nullable();
        $table->unsignedBigInteger('lead_id')->default(0);
        $table->string('date')->nullable();
        $table->string('time')->nullable();
        $table->text('description')->nullable();
        $table->timestamps();
        $table->softDeletes();
    });

    Schema::create('entrances', function (Blueprint $table) {
        $table->id();
        $table->string('contact')->nullable();
        $table->string('first_name')->nullable();
        $table->string('last_name')->nullable();
        $table->bigInteger('lead_detail_id')->nullable();
        $table->text('message')->nullable();
        $table->integer('campaign_id')->nullable();
        $table->integer('channel_id')->nullable();
        $table->integer('workspace_id')->nullable();
        $table->string('email')->nullable();
        $table->integer('status')->nullable();
        $table->string('address')->nullable();
        $table->string('zip_code')->nullable();
        $table->string('state')->nullable();
        $table->string('city')->nullable();
        $table->string('mark_color')->nullable();
        $table->string('webhook_type')->nullable();
        $table->string('message_id')->nullable();
        $table->string('webhook_name')->nullable();
        $table->integer('last_webhook_used')->default(1);
        $table->text('account_key')->nullable();
        $table->timestamps();
    });

    Schema::create('webhooks', function (Blueprint $table) {
        $table->increments('id');
        $table->bigInteger('lead_id');
        foreach (['CEL', 'Klay', 'WL', 'EN', 'MM', 'QUOTE', 'INST', 'LG', 'CS', 'BHC', 'YAIR', 'AVCHAD', 'MESSAGE'] as $col) {
            $table->string($col)->nullable();
        }
        $table->timestamp('STORE_DATE')->nullable();
        $table->timestamps();
    });

    Schema::create('sms_texts', function (Blueprint $table) {
        $table->id();
        $table->string('sms_word')->nullable();
        $table->timestamps();
    });

    Schema::create('constant_contacts', function (Blueprint $table) {
        $table->increments('id');
        $table->integer('lead_id')->nullable();
        $table->string('email_text')->nullable();
        $table->string('campaign')->nullable();
        $table->string('email_time')->nullable();
        $table->string('webhook_name')->nullable();
        $table->timestamps();
    });

    Schema::create('buy_numbers', function (Blueprint $table) {
        $table->increments('id');
        $table->string('contact_no')->nullable();
        $table->integer('user_id')->nullable();
        $table->timestamp('call_time')->nullable();
        $table->string('state')->nullable();
        $table->string('city')->nullable();
        $table->string('lock_number')->nullable();
        $table->timestamps();
    });

    Schema::create('track_buttons', function (Blueprint $table) {
        $table->increments('id');
        $table->integer('lead_id');
        $table->integer('user_id')->nullable();
        $table->string('create_date')->nullable();
        $table->string('duration')->nullable();
        $table->integer('status')->nullable();
        $table->string('button_text')->nullable();
        $table->longText('comments')->nullable();
        $table->timestamps();
    });

    Schema::create('lead_blast_runs', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('user_id')->nullable();
        $table->string('blast_type');
        $table->string('carrier_filter');
        $table->unsignedBigInteger('campaign_id')->nullable();
        $table->string('requested_count');
        $table->unsignedTinyInteger('min_ai_score')->nullable();
        $table->boolean('dnc_excluded')->default(true);
        $table->unsignedInteger('total_eligible')->default(0);
        $table->unsignedInteger('selected_count')->default(0);
        $table->unsignedInteger('excluded_locked_count')->default(0);
        $table->unsignedInteger('excluded_dnc_count')->default(0);
        $table->unsignedInteger('excluded_non_cell_count')->default(0);
        $table->string('csv_path')->nullable();
        $table->string('status')->default('generated');
        $table->timestamps();
    });

    Schema::create('lead_blast_items', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('lead_blast_run_id');
        $table->unsignedBigInteger('lead_id');
        $table->string('phone_normalized')->nullable();
        $table->unsignedTinyInteger('ai_score')->nullable();
        $table->timestamp('locked_until')->nullable();
        $table->timestamp('downloaded_at')->nullable();
        $table->timestamps();
        $table->unique(['lead_blast_run_id', 'lead_id']);
    });

    Schema::create('lead_ai_scores', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('lead_id');
        $table->unsignedTinyInteger('score')->nullable();
        $table->text('reason')->nullable();
        $table->timestamp('scored_at')->nullable();
        $table->timestamps();
        $table->unique('lead_id');
    });

    Schema::create('dnc_numbers', function (Blueprint $table) {
        $table->id();
        $table->string('phone_normalized')->unique();
        $table->string('source')->nullable();
        $table->text('notes')->nullable();
        $table->timestamps();
    });

    Schema::create('salesman_messages', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('admin_id');
        $table->unsignedBigInteger('salesman_id');
        $table->string('direction', 20)->default('outbound');
        $table->string('status', 30)->default('pending');
        $table->string('from_number')->nullable();
        $table->string('to_number')->nullable();
        $table->text('body');
        $table->string('telnyx_message_id')->nullable();
        $table->json('telnyx_payload')->nullable();
        $table->text('error_message')->nullable();
        $table->timestamp('read_at')->nullable();
        $table->timestamps();
    });

    Schema::create('entrance_auths', function (Blueprint $table) {
        $table->increments('id');
        $table->string('entrance_one_data')->nullable();
        $table->string('entrance_two_data')->nullable();
        $table->string('entrance_three_data')->nullable();
        $table->string('entrance_userIds')->nullable();
        $table->text('message_one')->nullable();
        $table->text('message_two')->nullable();
        $table->text('message_three')->nullable();
        $table->timestamps();
    });

    Schema::create('entrance_grp_messages', function (Blueprint $table) {
        $table->increments('id');
        $table->string('grp_name');
        $table->text('text');
        $table->string('unique_grp_id');
        $table->timestamps();
    });

    Schema::create('clone_entrances', function (Blueprint $table) {
        $table->id();
        $table->string('contact')->nullable();
        $table->string('first_name')->nullable();
        $table->string('last_name')->nullable();
        $table->unsignedBigInteger('lead_detail_id')->nullable();
        $table->text('message')->nullable();
        $table->integer('campaign_id')->nullable();
        $table->integer('channel_id')->nullable();
        $table->integer('workspace_id')->nullable();
        $table->string('email')->nullable();
        $table->integer('status')->nullable();
        $table->string('address')->nullable();
        $table->string('zip_code')->nullable();
        $table->string('state')->nullable();
        $table->string('city')->nullable();
        $table->string('mark_color')->nullable();
        $table->string('webhook_type')->nullable();
        $table->string('message_id')->nullable();
        $table->string('webhook_name')->nullable();
        $table->integer('last_webhook_used')->default(1);
        $table->text('account_key')->nullable();
        $table->timestamps();
    });

    Schema::create('basic_settings', function (Blueprint $table) {
        $table->increments('id');
        $table->integer('entrance_delay')->nullable();
        $table->integer('entrance_split')->nullable();
        $table->integer('lead_lock_time')->nullable();
        $table->integer('telynx_webhook_recieve')->nullable();
    });

    // Analytics mirror (Phase 10c). In tests the `analytics` connection is
    // pointed at this same sqlite database.
    Schema::create('analytics_customers', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('client_id')->unique();
        $table->string('policy_number')->nullable();
        $table->string('first_name')->nullable();
        $table->string('last_name')->nullable();
        $table->string('email')->nullable();
        $table->string('contact')->nullable();
        $table->string('city')->nullable();
        $table->string('state')->nullable();
        $table->string('zip_code')->nullable();
        $table->string('payment_structure')->nullable();
        $table->string('payment_type')->nullable();
        $table->string('installment')->nullable();
        $table->string('paid_installment')->nullable();
        $table->string('property_size_id')->nullable();
        $table->string('property_type_id')->nullable();
        $table->string('package_id')->nullable();
        $table->string('discount')->nullable();
        $table->decimal('total_contract_value', 15, 2)->default(0);
        $table->string('start_date')->nullable();
        $table->string('end_date')->nullable();
        $table->string('status')->nullable();
        $table->string('authorize_status')->nullable();
        $table->timestamp('created_at_client')->nullable();
        $table->timestamp('updated_at_client')->nullable();
        $table->timestamps();
    });

    Schema::create('analytics_sales_events', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('client_id')->unique();
        $table->unsignedBigInteger('customer_id')->nullable();
        $table->string('policy_number')->nullable();
        $table->date('sale_date')->nullable();
        $table->integer('sale_year')->nullable();
        $table->integer('sale_month')->nullable();
        $table->integer('sale_day')->nullable();
        $table->string('plan_type')->nullable();
        $table->integer('billing_term_months')->nullable();
        $table->decimal('total_contract_value', 15, 2)->default(0);
        $table->decimal('first_payment_amount', 15, 2)->nullable();
        $table->decimal('recurring_amount', 15, 2)->nullable();
        $table->boolean('is_recurring')->default(false);
        $table->unsignedBigInteger('campaign_id')->nullable();
        $table->string('channel')->nullable();
        $table->string('source_tag')->nullable();
        $table->timestamps();
    });

    Schema::create('analytics_payments', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('payment_id')->unique();
        $table->unsignedBigInteger('client_id')->nullable();
        $table->decimal('amount', 15, 2)->nullable();
        $table->boolean('is_claim_payment')->default(false);
        $table->boolean('is_refund_payment')->default(false);
        $table->boolean('is_success')->default(false);
        $table->timestamp('paid_at')->nullable();
        $table->integer('year')->nullable();
        $table->integer('month')->nullable();
        $table->integer('day')->nullable();
        $table->string('payment_method')->nullable();
        $table->string('payment_status_text')->nullable();
        $table->timestamps();
    });

    Schema::create('analytics_claims', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('claim_id')->unique();
        $table->unsignedBigInteger('client_id')->nullable();
        $table->string('claim_number')->nullable();
        $table->string('status')->nullable();
        $table->decimal('total_price', 15, 2)->default(0);
        $table->decimal('total_spent', 15, 2)->default(0);
        $table->timestamp('claim_created_at')->nullable();
        $table->timestamp('claim_updated_at')->nullable();
        $table->integer('year')->nullable();
        $table->integer('month')->nullable();
        $table->integer('day')->nullable();
        $table->timestamps();
    });

    Schema::create('blogs', function (Blueprint $table) {
        $table->id();
        $table->string('title');
        $table->string('slug')->unique();
        $table->text('excerpt')->nullable();
        $table->longText('content')->nullable();
        $table->string('image')->nullable();
        $table->boolean('is_published')->default(false);
        $table->timestamp('published_at')->nullable();
        $table->timestamps();
    });

    Schema::create('banners', function (Blueprint $table) {
        $table->id();
        $table->string('promo_code')->nullable();
        $table->string('percent_off')->nullable();
        $table->string('text_two')->nullable();
        $table->string('month_free')->nullable();
        $table->string('promo_code_color')->nullable();
        $table->string('percent_off_color')->nullable();
        $table->string('bg_color')->nullable();
        $table->string('bg_color_two')->nullable();
        $table->string('text_one_color')->nullable();
        $table->string('text_two_color')->nullable();
        $table->timestamps();
    });

    foreach (['buyer_sellers', 'partners'] as $leadFormTable) {
        Schema::create($leadFormTable, function (Blueprint $table) {
            $table->id();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('company_name')->nullable();
            $table->string('primary_phone')->nullable();
            $table->string('mobile_number')->nullable();
            $table->string('email')->nullable();
            $table->string('fax')->nullable();
            $table->string('street_address')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('zip')->nullable();
            $table->text('comment')->nullable();
            $table->string('status')->nullable();
            $table->timestamps();
        });
    }

    Schema::create('contact_us', function (Blueprint $table) {
        $table->id();
        $table->string('first_name')->nullable();
        $table->string('last_name')->nullable();
        $table->string('contact')->nullable();
        $table->text('description')->nullable();
        $table->string('email')->nullable();
        $table->string('status')->nullable();
        $table->timestamps();
    });

    Schema::create('lead_automation_logs', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('lead_id');
        $table->string('action_key', 64);
        $table->string('status', 20)->default('pending');
        $table->timestamp('sent_at')->nullable();
        $table->text('error_message')->nullable();
        $table->json('meta')->nullable();
        $table->timestamps();
        $table->unique(['lead_id', 'action_key']);
    });

    Schema::create('billing_settings', function (Blueprint $table) {
        $table->id();
        $table->string('key', 64)->unique();
        $table->text('value')->nullable();
        $table->timestamps();
    });

    Schema::create('analytics_leads', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('lead_id')->unique();
        $table->unsignedBigInteger('client_id')->nullable();
        $table->string('name')->nullable();
        $table->string('email')->nullable();
        $table->string('phone')->nullable();
        $table->string('source')->nullable();
        $table->string('campaign')->nullable();
        $table->string('status')->nullable();
        $table->timestamp('lead_created_at')->nullable();
        $table->integer('year')->nullable();
        $table->integer('month')->nullable();
        $table->integer('day')->nullable();
        $table->boolean('converted_to_client')->default(false);
        $table->timestamps();
    });

    Schema::create('email_signatures', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('user_id')->nullable();
        $table->string('name')->nullable();
        $table->string('subject')->nullable();
        $table->longText('eamil_text')->nullable();
        $table->timestamps();
    });

    Schema::create('sms_signatures', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('user_id')->nullable();
        $table->string('name')->nullable();
        $table->longText('message_text')->nullable();
        $table->timestamps();
    });

    Schema::create('voice_signatures', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('user_id')->nullable();
        $table->string('name')->nullable();
        $table->string('audio_file')->nullable();
        $table->timestamps();
    });

    Schema::create('converted_leads', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('campaign_id')->default(0);
        $table->string('user_id')->nullable();
        $table->integer('lead_id');
        $table->string('policy_number')->default(0);
        $table->string('first_name')->nullable();
        $table->string('last_name')->nullable();
        $table->string('contact')->nullable();
        $table->string('email')->nullable();
        $table->text('address')->nullable();
        $table->string('city')->nullable();
        $table->string('state')->nullable();
        $table->string('zip_code')->nullable();
        $table->text('notes')->nullable();
        $table->unsignedBigInteger('status')->default(0);
        $table->unsignedBigInteger('reminder_id')->default(0);
        $table->string('reminder_status')->default(0);
        $table->unsignedBigInteger('property_size')->default(0);
        $table->unsignedBigInteger('property_type')->default(0);
        $table->unsignedBigInteger('package')->default(0);
        $table->unsignedBigInteger('services_call_fee')->default(0);
        $table->unsignedBigInteger('live_charges')->default(0);
        $table->text('additional_comments')->nullable();
        $table->unsignedBigInteger('apply_discounts')->default(0);
        $table->unsignedBigInteger('payment_terms')->default(0);
        $table->unsignedBigInteger('instalments')->default(0);
        $table->bigInteger('sale_man')->nullable();
        $table->string('longitude')->nullable();
        $table->string('latitude')->nullable();
        $table->string('email_date_time')->nullable();
        $table->tinyInteger('mail_type')->nullable();
        $table->string('contact_text')->nullable();
        $table->string('phone_company')->nullable();
        $table->boolean('show_status')->default(true);
        $table->boolean('color_change')->default(false);
        $table->timestamps();
        $table->softDeletes();
    });

    Schema::create('converted_lead_statuses', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('campaign_id')->nullable();
        $table->unsignedBigInteger('converted_lead_id')->nullable();
        $table->unsignedBigInteger('onverted_lead_id')->nullable();
        $table->unsignedBigInteger('lead_id')->nullable();
        $table->unsignedBigInteger('status')->nullable();
        $table->text('notes')->nullable();
        $table->timestamps();
    });

    Schema::create('salesman_payrolls', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('user_id');
        $table->date('period_start');
        $table->date('period_end');
        $table->date('pay_date');
        $table->unsignedInteger('sales_count')->default(0);
        $table->decimal('gross', 12, 2)->default(0);
        $table->decimal('spiff', 12, 2)->default(0);
        $table->decimal('net', 12, 2)->default(0);
        $table->unsignedBigInteger('run_by_user_id')->nullable();
        $table->string('run_by_email', 190)->nullable();
        $table->timestamps();
    });

    Schema::create('sale_commissions', function (Blueprint $table) {
        $table->increments('id');
        $table->integer('user_id')->nullable();
        $table->integer('client_id')->nullable();
        $table->string('policy_number')->nullable();
        $table->integer('payment_structure')->nullable();
        $table->integer('installment')->nullable();
        $table->float('price')->nullable();
        $table->float('commision_price')->nullable();
        $table->timestamp('store_date')->nullable();
        $table->string('total_year')->nullable();
        $table->string('type')->nullable();
        $table->boolean('is_paid')->default(false);
        $table->boolean('is_cancel')->default(false);
        $table->boolean('is_cancel_deducted')->default(false);
        $table->timestamps();
    });

    Schema::create('client_earnings', function (Blueprint $table) {
        $table->increments('id');
        $table->integer('client_id');
        $table->float('total_earn')->default(0);
        $table->float('total_profit')->default(0);
        $table->timestamps();
    });

    Schema::create('salesman_activities', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('user_id')->nullable();
        $table->unsignedBigInteger('client_id')->nullable();
        $table->json('button_text')->nullable();
        $table->string('status')->nullable();
        $table->string('create_date')->nullable();
        $table->timestamps();
    });

    Schema::create('track_salesman_actions', function (Blueprint $table) {
        $table->increments('id');
        $table->bigInteger('lead_id');
        $table->integer('user_id');
        $table->string('click_to_call')->nullable();
        $table->string('save')->nullable();
        $table->string('send_sms')->nullable();
        $table->string('send_mail')->nullable();
        $table->string('voice')->nullable();
        $table->string('convert_to_client')->nullable();
        $table->string('lead_status')->nullable();
        $table->timestamp('STORE_DATE')->nullable();
        $table->timestamps();
    });
}

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});
