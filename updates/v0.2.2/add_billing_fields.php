<?php

use Winter\Storm\Database\Schema\Blueprint;
use Winter\Storm\Database\Updates\Migration;
use Winter\Storm\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        // Add billing fields to projects
        if (!Schema::hasColumn('avalanchestudio_avalanchecrm_projects', 'billing_type')) {
            Schema::table('avalanchestudio_avalanchecrm_projects', function (Blueprint $table) {
                $table->string('billing_type')->default('non_billable')->after('status');
                $table->decimal('hourly_rate', 10, 2)->nullable()->after('billing_type');
                $table->decimal('fixed_price', 10, 2)->nullable()->after('hourly_rate');
            });
        }

        // Add billing fields to tasks
        if (!Schema::hasColumn('avalanchestudio_avalanchecrm_tasks', 'is_billable')) {
            Schema::table('avalanchestudio_avalanchecrm_tasks', function (Blueprint $table) {
                $table->boolean('is_billable')->default(false)->after('sort_order');
                $table->decimal('hours', 8, 2)->nullable()->after('is_billable');
                $table->decimal('hourly_rate', 10, 2)->nullable()->after('hours');
                $table->boolean('is_invoiced')->default(false)->after('hourly_rate');
            });
        }

        // Add notes to invoices
        if (!Schema::hasColumn('avalanchestudio_avalanchecrm_invoices', 'notes')) {
            Schema::table('avalanchestudio_avalanchecrm_invoices', function (Blueprint $table) {
                $table->text('notes')->nullable()->after('due_date');
            });
        }

        // Create invoice items table
        if (!Schema::hasTable('avalanchestudio_avalanchecrm_invoice_items')) {
        Schema::create('avalanchestudio_avalanchecrm_invoice_items', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('invoice_id')->unsigned();
            $table->integer('task_id')->unsigned()->nullable();
            $table->string('description');
            $table->decimal('quantity', 8, 2)->default(1);
            $table->decimal('unit_price', 10, 2)->default(0);
            $table->decimal('amount', 10, 2)->default(0);
            $table->timestamps();

            $table->index('invoice_id');
            $table->index('task_id');
        });
        }
    }

    public function down()
    {
        Schema::dropIfExists('avalanchestudio_avalanchecrm_invoice_items');

        Schema::table('avalanchestudio_avalanchecrm_invoices', function (Blueprint $table) {
            $table->dropColumn('notes');
        });

        Schema::table('avalanchestudio_avalanchecrm_tasks', function (Blueprint $table) {
            $table->dropColumn(['is_billable', 'hours', 'hourly_rate', 'is_invoiced']);
        });

        Schema::table('avalanchestudio_avalanchecrm_projects', function (Blueprint $table) {
            $table->dropColumn(['billing_type', 'hourly_rate', 'fixed_price']);
        });
    }
};
