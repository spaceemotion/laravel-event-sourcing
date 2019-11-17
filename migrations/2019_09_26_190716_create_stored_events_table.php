<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Spaceemotion\LaravelEventSourcing\EventStore\DatabaseEventStore;

final class CreateStoredEventsTable extends Migration
{
    public function up(): void
    {
        Schema::create('stored_events', static function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->unsignedInteger(DatabaseEventStore::FIELD_VERSION);
            $table->string(DatabaseEventStore::FIELD_AGGREGATE_ID)->index();
            $table->string(DatabaseEventStore::FIELD_EVENT_TYPE)->index();
            $table->json(DatabaseEventStore::FIELD_PAYLOAD);
            $table->json(DatabaseEventStore::FIELD_META_DATA);
            $table->timestamp(DatabaseEventStore::FIELD_CREATED_AT);

            // Prevent concurrency issues
            $table->unique([
                DatabaseEventStore::FIELD_AGGREGATE_ID,
                DatabaseEventStore::FIELD_VERSION,
            ]);

            // Add index for faster snapshot lookup
            $table->index([
                DatabaseEventStore::FIELD_AGGREGATE_ID,
                DatabaseEventStore::FIELD_EVENT_TYPE,
            ]);
        });
    }
}
