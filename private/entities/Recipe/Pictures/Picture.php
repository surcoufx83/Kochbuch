<?php

namespace Surcouf\Cookbook\Recipe\Pictures;

use DateTime;
use Imagick;
use JsonSerializable;
use Surcouf\Cookbook\Helper\FilesystemHelper;
use Surcouf\Cookbook\Helper\HashHelper;
use Surcouf\Cookbook\User\User;

if (!defined('CORE2'))
  exit;

class Picture implements JsonSerializable
{

  protected string|int $picture_id, $recipe_id, $picture_sortindex, $picture_width, $picture_height;
  protected string|int|null $user_id;
  protected string $picture_name, $picture_description, $picture_hash, $picture_filename, $picture_full_path;
  protected string|DateTime|null $picture_uploaded;

  private $fileexists = true;
  private $filesize = 0;

  public function __construct(?array $record = null, bool $initSelf = false)
  {
    if (!is_null($record)) {
      $this->picture_id = intval($record['picture_id']);
      $this->recipe_id = intval($record['recipe_id']);
      $this->user_id = !is_null($record['user_id']) ? intval($record['user_id']) : null;
      $this->picture_sortindex = intval($record['picture_sortindex']);
      $this->picture_name = $record['picture_name'];
      $this->picture_description = $record['picture_description'];
      $this->picture_hash = $record['picture_hash'];
      $this->picture_filename = $record['picture_filename'];
      $this->picture_full_path = $record['picture_full_path'];
      $this->picture_uploaded = !is_null($record['picture_uploaded']) ? new DateTime($record['picture_uploaded']) : null;
      $this->picture_width = !is_null($record['picture_width']) ? intval($record['picture_width']) : null;
      $this->picture_height = !is_null($record['picture_height']) ? intval($record['picture_height']) : null;
    } else {
      $this->picture_id = intval($this->picture_id);
      $this->recipe_id = intval($this->recipe_id);
      $this->user_id = !is_null($this->user_id) ? intval($this->user_id) : null;
      $this->picture_sortindex = intval($this->picture_sortindex);
      $this->picture_uploaded = !is_null($this->picture_uploaded) ? new DateTime($this->picture_uploaded) : null;
    }
    $this->fileexists = @file_exists($this->picture_full_path);
    if ($this->fileexists) {
      $this->filesize = @filesize($this->picture_full_path);
    }
    if ($initSelf) {
      $this->picture_hash = HashHelper::hash(join([
        $this->recipe_id,
        $this->picture_name,
        $this->picture_full_path,
        $this->filesize,
      ]));
      $this->initDimensions();
    }
  }

  /* public function calculateHash(): string
  {
    global $Controller;
    $data = [
      $this->picture_id,
      $this->recipe_id,
      $this->picture_name,
      $this->picture_filename,
    ];
    $this->picture_hash = HashHelper::hash(join($data));
    $this->changes['picture_hash'] = $this->picture_hash;
    $Controller->updateDbObject($this);
    return $this->picture_hash;
  } */

  public static function create(int $recipeId, int $index, array $file): ?self
  {
    global $Controller;
    $picture = new self([
      'picture_id' => 0,
      'recipe_id' => $recipeId,
      'user_id' => $Controller->User()->getId(),
      'picture_sortindex' => $index,
      'picture_name' => $file['name'],
      'picture_description' => '',
      'picture_hash' => '',
      'picture_filename' => $file['name'],
      'picture_full_path' => $file['tmp_name'],
      'picture_uploaded' => null,
      'picture_width' => 0,
      'picture_height' => 0,
    ], true);

    return $picture;
  }

  public function createThumbnail(): bool
  {
    global $Controller;
    $img = new Imagick();
    $raw = \file_get_contents($this->getFullpath());
    $img->readImageBlob($raw);
    $orientation = $img->getImageOrientation();
    switch ($orientation) {
      case imagick::ORIENTATION_BOTTOMRIGHT:
        $img->rotateimage("#000", 180); // rotate 180 degrees
        break;
      case imagick::ORIENTATION_RIGHTTOP:
        $img->rotateimage("#000", 90); // rotate 90 degrees CW
        break;
      case imagick::ORIENTATION_LEFTBOTTOM:
        $img->rotateimage("#000", -90); // rotate 90 degrees CCW
        break;
    }
    $img->setImageOrientation(imagick::ORIENTATION_TOPLEFT);
    $width = $img->getImageWidth();
    $height = $img->getImageHeight();
    $img->setImageCompressionQuality(85);
    if ($Controller->Config()->System('Thumbnails', 'Resize') === true)
      $img->thumbnailImage($Controller->Config()->System('Thumbnails', 'Width'), $Controller->Config()->System('Thumbnails', 'Height'), true);
    else
      $img->thumbnailImage($width, $height);
    $img->setImageFormat('jpeg');
    $img->setSamplingFactors(['2x2', '1x1', '1x1']);
    $profiles = $img->getImageProfiles('icc', true);
    $img->stripImage();
    if (!empty($profiles))
      $img->profileImage('icc', $profiles['icc']);
    $img->setInterlaceScheme(Imagick::INTERLACE_JPEG);
    $img->setColorspace(Imagick::COLORSPACE_SRGB);
    $img->writeImage($this->getFullpath(true));
    $img->destroy();
    return true;
  }

  public function getDescription(): string
  {
    return $this->picture_description;
  }

  public function getExtension(): string
  {
    return pathinfo($this->picture_filename, PATHINFO_EXTENSION);
  }

  public function getFilename(bool $thumbnail = false): string
  {
    return sprintf('%s%s.%s', pathinfo($this->picture_filename, PATHINFO_FILENAME), ($thumbnail ? '-thb' : ''), ($thumbnail ? 'jpg' : $this->getExtension()));
  }

  public function getFolderName(): string
  {
    return substr($this->picture_filename, 0, 2);
  }

  public function getFullpath(bool $thumbnail = false): string
  {
    return FilesystemHelper::paths_combine(pathinfo($this->picture_full_path, PATHINFO_DIRNAME), $this->getFilename($thumbnail));
  }

  public function getHash(): string
  {
    return $this->picture_hash;
  }

  public function getHeight(): int
  {
    return $this->picture_height;
  }

  public function getId(): int
  {
    return $this->picture_id;
  }

  public function getIndex(): int
  {
    return $this->picture_sortindex;
  }

  public function getName(): string
  {
    return $this->picture_name;
  }

  public function getPublicPath(bool $thumbnail = false): string
  {
    return '/pictures/cbimages/' . $this->getFolderName() . '/' . $this->getFilename($thumbnail);
  }

  public function getRecipeId(): int
  {
    return $this->recipe_id;
  }

  public function getUploadDate(): DateTime
  {
    return $this->picture_uploaded;
  }

  public function getUser(): ?User
  {
    global $Controller;
    return User::load($this->user_id);
  }

  public function getUserId(): ?int
  {
    return $this->user_id;
  }

  public function getWidth(): int
  {
    if (is_null($this->picture_width))
      $this->initDimensions();
    return $this->picture_width;
  }

  private function initDimensions(): void
  {
    global $Controller;
    $data = @getimagesize($this->picture_full_path);
    if ($data) {
      $this->picture_width = $data[0];
      $this->picture_height = $data[1];
    } else {
      $Controller->logi(__CLASS__, __METHOD__, __LINE__, 'Error getting picture size', $this);
    }
  }

  public function hasHash(): bool
  {
    return !is_null($this->picture_hash);
  }

  public function jsonSerialize(): mixed
  {
    global $Controller;
    return [
      'description' => $this->picture_description,
      'id' => $this->picture_id,
      'index' => $this->picture_sortindex,
      'link' => $this->getPublicPath(),
      'thumbnail' => $this->getPublicPath(true),
      'name' => $this->picture_name,
      'uploaded' => !is_null($this->picture_uploaded) ? $this->picture_uploaded->format('c') : null,
      'uploadFile' => null,
      'uploader' => (!is_null($this->user_id) ? $this->getUser()->getJsonObj() : null),
      'w' => $this->getWidth(),
      'h' => $this->getHeight(),
    ];
  }

  public function moveToPublicFolder(): bool
  {
    global $Controller;
    if ($this->picture_id !== 0) {
      $Controller->loge(__CLASS__, __METHOD__, __LINE__, 'Picture::moveToPublicFolder() must not be called for persisted pictures.', $this);
      return false;
    }
    $targetfilename = sprintf('%s%s.%s', $this->picture_hash, $this->recipe_id, $this->getExtension());
    $targetfolder = sprintf($Controller->Config()->System('Pictures', 'PublicFolder'), substr($this->picture_hash, 0, 2));
    if (!@file_exists($targetfolder)) {
      if (!@mkdir($targetfolder, 0777, true)) {
        $Controller->loge(__CLASS__, __METHOD__, __LINE__, 'Picture::moveToPublicFolder() unable to create folder', [$targetfolder, $this]);
        return false;
      }
    }
    $targetfile = sprintf('%s/%s', $targetfolder, $targetfilename);
    if (@copy($this->picture_full_path, $targetfile)) {
      $Controller->addFileToTransaction($targetfile);
      $this->picture_filename = $targetfilename;
      $this->picture_full_path = $targetfile;
      $this->createThumbnail();
      $Controller->addFileToTransaction($this->getFullpath(true));
      return true;
    }
    $Controller->loge(__CLASS__, __METHOD__, __LINE__, 'Picture::moveToPublicFolder() failed copying file', $this);
    return false;
  }

  public function save(array &$response): bool
  {
    global $Controller;
    if ($this->picture_id !== 0) {
      $Controller->loge(__CLASS__, __METHOD__, __LINE__, 'Picture::save() must not be called for persisted pictures, use update() instead.', $this);
      return false;
    }
    $stmt = $Controller->prepare('INSERT INTO recipe_pictures(recipe_id, user_id, picture_sortindex, picture_name,'
      . ' picture_description, picture_hash, picture_filename, picture_full_path, picture_width, picture_height)'
      . ' VALUES(?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');

    $stmt->bind_param(
      'iiisssssii',
      $this->recipe_id,
      $this->user_id,
      $this->picture_sortindex,
      $this->picture_name,
      $this->picture_description,
      $this->picture_hash,
      $this->picture_filename,
      $this->picture_full_path,
      $this->picture_width,
      $this->picture_height,
    );

    if (!$stmt->execute()) {
      $response = [
        'errorCode' => 8,
        'recipe' => null,
        'userError' => false,
      ];
      return false;
    }

    $this->picture_id = $stmt->insert_id;
    return true;

  }

}