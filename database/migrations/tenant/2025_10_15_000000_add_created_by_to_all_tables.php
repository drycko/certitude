<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up()
  {
    // tables without created_by column (main tables only - exclude pivot, roles, permissions and settings tables)
    $tables = [
      'file_types',
      'powerbi_links',
      'commodities',
      'fbos',
      'powerbi_link_types',
      'users',
    ];

    foreach ($tables as $table) {
      if (!Schema::hasColumn($table, 'created_by')) {
        Schema::table($table, function (Blueprint $table) {
          $table->unsignedBigInteger('created_by')->nullable()->after('updated_at');
        });
      }
    }
  }

  public function down()
  {
    $tables = [
      'file_types',
      'powerbi_links',
      'commodities',
      'fbos',
      'powerbi_link_types',
      'users',
    ];

    foreach ($tables as $table) {
      if (Schema::hasColumn($table, 'created_by')) {
        Schema::table($table, function (Blueprint $table) {
          $table->dropColumn('created_by');
        });
      }
    }
  }
};
