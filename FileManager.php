<?php

namespace jorique\yiistatic;

use Yii;
use InvalidArgumentException;
use yii\base\InvalidConfigException;
use yii\base\Component;
use yii\helpers\FileHelper;
use yii\web\ServerErrorHttpException;
use yii\web\UploadedFile;
use yii\web\NotFoundHttpException;
use yii\imagine\Image;
use Imagine\Image\ManipulatorInterface;

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

	const TN_ADAPTIVE = ManipulatorInterface::THUMBNAIL_OUTBOUND;
	const TN_EXACT = ManipulatorInterface::THUMBNAIL_INSET;

	private $_storages = [];

	public $storages = [];
	public $baseUrl = null;
	public $resizeCacheFolder = 'resize_cache';

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
		if($this->baseUrl === null) {
			$this->baseUrl = '/';
		}
		else {
			$this->baseUrl = rtrim($this->baseUrl, '/').'/';
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
		#TODO пока хардкором
		//$file->storage_id = $storageId;
		$file->storage_id = 1;
		if($file->save()) {
			$newName = sprintf('%010d', $file->id);
			$newName = substr($newName, -3).'/'.substr($newName, -6, 3).'/'.$newName;
			$ext = $uploadedFile->extension;
			if($ext) {
				$newName = $newName.'.'.mb_strtolower($ext);
			}

			$storage = $this->getStorage($storageId);
			#TODO не учитывает разные стораджи
			$stream = fopen($file->path, 'r+');
			if($storage->writeStream($newName, $stream)) {
				$file->path = $newName;
				$file->temp = 1;
				if($file->save()) {
					return $file;
				}
			}
		}
		else {
			print_r($file->errors);
		}
		return false;
	}

	public function getUrl($id) {
		$file = File::findOne($id);
		if(!$file) {
			throw new NotFoundHttpException('Image with id '.$id.' not found');
		}
		return $this->baseUrl.$file->path;
	}

	private function getResizePath($path, $sizes, $type) {
		$path = explode(DIRECTORY_SEPARATOR, $path);
		$fileName = end($path);
		$fileName = explode('.', $fileName);
		$fileName[0] = $fileName[0].'_'.$sizes[0].'_'.$sizes[1].'_'.$type;
		$path[sizeof($path)-1] = implode('.', $fileName);
		$path = implode(DIRECTORY_SEPARATOR, $path);
		return $this->resizeCacheFolder.'/'.$path;
	}

	public function resizeGet($id, $sizes, $type=null) {
		$type = $type ?: static::TN_ADAPTIVE;
		$file = File::findOne($id);
		if(!$file) {
			throw new NotFoundHttpException('Image with id '.$id.' not found');
		}
		$resizePath = $this->getResizePath($file->path, $sizes, $type);

		if(!$this->has($resizePath)) {
			$stream = tmpfile();
			$tmpName = stream_get_meta_data($stream);
			$tmpName = $tmpName['uri'];

			$ext = explode('.', $file->path);
			$ext = end($ext);
			$basePath = $this->getStorage($this->defaultStorage)->path;
			$basePath = FileHelper::normalizePath($basePath);
			$path = $basePath.'/'.$file->path;
			if(!Image::thumbnail($path, $sizes[0], $sizes[1], $type)->save($tmpName, ['format' => $ext])) {
				throw new ServerErrorHttpException('Saving thumbnail tmp error');
			}
			if(!$this->writeStream($resizePath, $stream)) {
				throw new ServerErrorHttpException('Saving thumbnail tmp error');
			}
		}
		return $this->baseUrl.$resizePath;
	}
}