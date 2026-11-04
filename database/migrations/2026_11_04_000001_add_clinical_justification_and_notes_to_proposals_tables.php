<?php

declare(strict_types=1);

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
        Schema::table('proposals', function (Blueprint $table): void {
            $table->text('notes')->nullable()->after('period_year');
        });

        Schema::table('proposal_items', function (Blueprint $table): void {
            $table->text('clinical_justification')->nullable()->after('principal_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('proposal_items', function (Blueprint $table): void {
            $table->dropColumn('clinical_justification');
        });

        Schema::table('proposals', function (Blueprint $table): void {
            $table->dropColumn('notes');
        });
    }
};
