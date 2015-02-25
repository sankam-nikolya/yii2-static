<?php

namespace jorique\yiistatic;

use Yii;
use InvalidArgumentException;
use yii\base\InvalidConfigException;
use yii\base\Component;
use yii\helpers\FileHelper;
use yii\validators\ImageValidator;
use yii\web\UploadedFile;

/**
 * FileManager
 *
 * @method \League\Flysystem\FilesystemInterface addPlugin(\League\Flysystem\PluginInterface $plugin)
 * @method void assertAbsent(string $path)
 * @method void assertPresent(string $path)
 * @method boolean copy(string $path, string $newpath)
 * @method boolean createDir(string $dirname, array $config = null)
 * @method boolean delete(string $path)
 * @method boolean deleteDir(string $dirname)
 * @method \League\Flysystem\Handler get(string $path, \League\Flysystem\Handler $handler = null)
 * @method \League\Flysystem\AdapterInterface getAdapter()
 * @method \League\Flysystem\Config getConfig()
 * @method array|false getMetadata(string $path)
 * @method string|false getMimetype(string $path)
 * @method integer|false getSize(string $path)
 * @method integer|false getTimestamp(string $path)
 * @method string|false getVisibility(string $path)
 * @method array getWithMetadata(string $path, array $metadata)
 * @method boolean has(string $path)
 * @method array listContents(string $directory = '', boolean $recursive = false)
 * @method array listFiles(string $path = '', boolean $recursive = false)
 * @method array listPaths(string $path = '', boolean $recursive = false)
 * @method array listWith(array $keys = [], $directory = '', $recursive = false)
 * @method boolean put(string $path, string $contents, array $config = [])
 * @method boolean putStream(string $path, resource $resource, array $config = [])
 * @method string|false read(string $path)
 * @method string|false readAndDelete(string $path)
 * @method resource|false readStream(string $path)
 * @method boolean rename(string $path, string $newpath)
 * @method boolean setVisibility(string $path, string $visibility)
 * @method boolean update(string $path, string $contents, array $config = [])
 * @method boolean updateStream(string $path, resource $resource, array $config = [])
 * @method boolean write(string $path, string $contents, array $config = [])
 * @method boolean writeStream(string $path, resource $resource, array $config = [])
 */
class FileManager extends Component {

	private $_storages = [];

	public $storages = [];

	public $defaultStorage;

	/**
	 * Init component
	 * @throws \yii\base\InvalidConfigException
	 */
	public function init() {
		if(!$this->storages) {
			throw new InvalidConfigException('Storages are not defined');
		}
		if(!$this->defaultStorage) {
			throw new InvalidConfigException('Default storage is not defined');
		}
		if(!isset($this->storages[$this->defaultStorage])) {
			throw new InvalidConfigException('Default storage is not defined in storages list');
		}
	}

	/**
	 * Returns storage object
	 * @param $id
	 * @return \creocoder\flysystem\Filesystem;
	 * @throws \InvalidArgumentException
	 */
	public function getStorage($id) {
		if(!isset($this->_storages[$id])) {
			if(!isset($this->storages[$id])) {
				throw new InvalidArgumentException('Storage '.$id.' not defined');
			}
			$this->_storages[$id] = Yii::createObject($this->storages[$id]);
		}
		return $this->_storages[$id];
	}

	public function __call($method, $params) {
		$storage = $this->getStorage($this->defaultStorage);
		return call_user_func_array([$storage, $method], $params);
	}

	public function save(UploadedFile $uploadedFile, $storageId=null) {
		$storageId = $storageId ?: $this->defaultStorage;

		$file = new File;
		$file->path = $uploadedFile->tempName;
		$file->size = filesize($file->path);
		$file->mime = FileHelper::getMimeType($file->path);

		# image params
		$imageInfo = getimagesize($file->path);
		if($imageInfo) {
			list($width, $height) = $imageInfo;
			if($width && $height) {
				$file->width = $width;
				$file->height = $height;
			}
		}

		$file->temp = 1;
		$file->original_name = $uploadedFile->name;
		$file->storage_id = $storageId;
		if($file->save()) {
			$newName = sprintf('%010d', $file->id);
			$newName = substr($newName, -3).'/'.substr($newName, -6, 3).'/'.$newName;
			$ext = $uploadedFile->extension;
			if($ext) {
				$newName = $newName.'.'.mb_strtolower($ext);
			}

			$storage = $this->getStorage($storageId);
			#TODO не учитывает разные стораджи
			if($storage->copy($file->path, $newName)) {
				$file->path = $newName;
				$file->temp = 1;
				return $file->save();
			}
		}
		return false;
	}
}