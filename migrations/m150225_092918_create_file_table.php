<?php

use yii\db\Schema;
use yii\db\Migration;

class m150225_092918_create_file_table extends Migration {
	public function up() {
		$this->createTable('{{%file}}', [
			'id' => Schema::TYPE_PK,
			'path' => Schema::TYPE_STRING.' NOT NULL',
			'size' => Schema::TYPE_INTEGER.' NOT NULL',
			'mime' => Schema::TYPE_STRING,
			'width' => Schema::TYPE_INTEGER,
			'height' => Schema::TYPE_INTEGER,
			'create_at' => Schema::TYPE_TIMESTAMP.' DEFAULT CURRENT_TIMESTAMP',
			'update_at' => Schema::TYPE_TIMESTAMP,
			'temp' => Schema::TYPE_BOOLEAN,
			'original_name' => Schema::TYPE_STRING.' NOT NULL',
			'storage_id' => Schema::TYPE_SMALLINT.' NOT NULL'
		]);
	}

	public function down() {
		echo "m150225_092918_create_file_table cannot be reverted.\n";

		return false;
	}
}
