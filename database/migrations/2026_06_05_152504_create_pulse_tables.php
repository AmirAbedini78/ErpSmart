<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Laravel\Pulse\Support\PulseMigration;

return new class extends PulseMigration
{
    public function up(): void
    {
        if (! $this->shouldRun()) {
            return;
        }

        Schema::create('pulse_values', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('timestamp');
            $table->string('type');
            $table->mediumText('key');

            match ($this->driver()) {
                'mariadb', 'mysql' => $table->char('key_hash', 16)->charset('binary')->virtualAs('unhex(md5(`key`))'),
                'pgsql' => $table->uuid('key_hash')->storedAs('md5("key")::uuid'),
                'sqlite' => $table->string('key_hash'),
            };

            $table->mediumText('value');

            $table->index('timestamp', 'pv_ts_idx');
            $table->index('type', 'pv_type_idx');
            $table->unique(['type', 'key_hash'], 'pv_type_key_uq');
        });

        Schema::create('pulse_entries', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('timestamp');
            $table->string('type');
            $table->mediumText('key');

            match ($this->driver()) {
                'mariadb', 'mysql' => $table->char('key_hash', 16)->charset('binary')->virtualAs('unhex(md5(`key`))'),
                'pgsql' => $table->uuid('key_hash')->storedAs('md5("key")::uuid'),
                'sqlite' => $table->string('key_hash'),
            };

            $table->bigInteger('value')->nullable();

            $table->index('timestamp', 'pe_ts_idx');
            $table->index('type', 'pe_type_idx');
            $table->index('key_hash', 'pe_key_idx');
            $table->index(
                ['timestamp', 'type', 'key_hash', 'value'],
                'pe_agg_idx'
            );
        });

        Schema::create('pulse_aggregates', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('bucket');
            $table->unsignedMediumInteger('period');
            $table->string('type');
            $table->mediumText('key');

            match ($this->driver()) {
                'mariadb', 'mysql' => $table->char('key_hash', 16)->charset('binary')->virtualAs('unhex(md5(`key`))'),
                'pgsql' => $table->uuid('key_hash')->storedAs('md5("key")::uuid'),
                'sqlite' => $table->string('key_hash'),
            };

            $table->string('aggregate');
            $table->decimal('value', 20, 2);
            $table->unsignedInteger('count')->nullable();

            $table->unique(
                ['bucket', 'period', 'type', 'aggregate', 'key_hash'],
                'pa_bucket_uq'
            );

            $table->index(
                ['period', 'bucket'],
                'pa_period_bucket_idx'
            );

            $table->index(
                'type',
                'pa_type_idx'
            );

            $table->index(
                ['period', 'type', 'aggregate', 'bucket'],
                'pa_agg_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pulse_values');
        Schema::dropIfExists('pulse_entries');
        Schema::dropIfExists('pulse_aggregates');
    }
};