<?php

namespace jorique\yiistatic;

use Yii;

/**
 * This is the model class for table "{{%file}}".
 *
 * @property integer $id
 * @property string $path
 * @property integer $size
 * @property string $mime
 * @property integer $width
 * @property integer $height
 * @property string $create_at
 * @property string $update_at
 * @property integer $temp
 * @property string $original_name
 * @property integer $storage_id
 */
class File extends \yii\db\ActiveRecord {
	/**
	 * @inheritdoc
	 */
	public static function tableName() {
		return '{{%file}}';
	}

	/**
	 * @inheritdoc
	 */
	public function rules() {
		return [
			[['path', 'size', 'original_name', 'storage_id'], 'required'],
			[['size', 'width', 'height', 'temp', 'storage_id'], 'integer'],
			[['path', 'mime', 'original_name'], 'string', 'max' => 255]
		];
	}

	/**
	 * @inheritdoc
	 */
	public function attributeLabels() {
		return [
			'id' => 'ID',
			'path' => 'Path',
			'size' => 'Size',
			'mime' => 'Mime',
			'width' => 'Width',
			'height' => 'Height',
			'create_at' => 'Create At',
			'update_at' => 'Update At',
			'temp' => 'Temp',
			'original_name' => 'Original Name',
			'storage_id' => 'Storage ID',
		];
	}

	public function beforeDelete() {
		if(parent::beforeDelete()) {
			if(Yii::$app->fileManager->delete($this->path)) {
				#TODO рекрсивно удалять директории
				return true;
			}
		}
		return false;
	}
}
