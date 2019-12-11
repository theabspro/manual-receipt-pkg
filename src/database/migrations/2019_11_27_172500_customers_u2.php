<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class ReceiptsU2 extends Migration {
	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up() {
		Schema::table('receipts', function (Blueprint $table) {
			$table->string('gst_number', 191)->nullable()->after('company_id');
			$table->unsignedInteger('created_by_id')->nullable()->after('gst_number');
			$table->unsignedInteger('updated_by_id')->nullable()->after('created_by_id');
			$table->unsignedInteger('deleted_by_id')->nullable()->after('updated_by_id');
			$table->timestamps();
			$table->softdeletes();

			$table->foreign('created_by_id')->references('id')->on('users')->onDelete('SET NULL')->onUpdate('cascade');
			$table->foreign('updated_by_id')->references('id')->on('users')->onDelete('SET NULL')->onUpdate('cascade');
			$table->foreign('deleted_by_id')->references('id')->on('users')->onDelete('SET NULL')->onUpdate('cascade');

			$table->unique(["company_id", "gst_number"]);
		});
	}

	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down() {
		Schema::table('receipts', function (Blueprint $table) {
			$table->dropForeign('receipts_created_by_id_foreign');
			$table->dropForeign('receipts_updated_by_id_foreign');
			$table->dropForeign('receipts_deleted_by_id_foreign');

			$table->dropUnique('receipts_company_id_gst_number_unique');

			$table->dropColumn('gst_number');
			$table->dropColumn('created_by_id');
			$table->dropColumn('updated_by_id');
			$table->dropColumn('deleted_by_id');
			$table->dropColumn('timestamps');
			$table->dropColumn('softdeletes');

		});

	}
}
