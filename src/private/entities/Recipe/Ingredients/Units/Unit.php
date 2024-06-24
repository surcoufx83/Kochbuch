<?php

namespace Surcouf\Cookbook\Recipe\Ingredients\Units;

use DateTime;
use JsonSerializable;

if (!defined('CORE2'))
  exit;

class Unit implements JsonSerializable
{

  protected string|int $unit_id;
  protected string|bool $localized;
  protected string|int|null $useunit_id;
  protected string|DateTime $created, $updated;
  protected string $unit_name;
  protected null|string|array $unit_name_de;
  protected null|string|array $unit_name_en;

  private static $Units = [];

  public function __construct(?array $record = null)
  {
    if (!is_null($record)) {
      $this->unit_id = intval($record['unit_id']);
      $this->useunit_id = array_key_exists('useunit_id', $record) && !is_null($record['useunit_id']) ? intval($record['useunit_id']) : null;
      $this->unit_name = $record['unit_name'];
      $this->created = array_key_exists('created', $record) ? new DateTime($record['created']) : new DateTime('now');
      $this->updated = array_key_exists('updated', $record) ? new DateTime($record['updated']) : new DateTime('now');
    } else {
      $this->unit_id = intval($this->unit_id);
      $this->useunit_id = !is_null($this->useunit_id) ? intval($this->useunit_id) : null;
      $this->created = new DateTime($this->created);
      $this->updated = new DateTime($this->updated);
    }
  }

  public static function createUnit(string $name): ?self
  {
    global $Controller;
    $name = trim($name);
    if ($name == '')
      return null;
    $query = 'INSERT INTO units(unit_name) VALUES(?)';
    $stmt = $Controller->prepare($query);
    $stmt->bind_param('s', $name);
    if (!$stmt->execute()) {
      $Controller->cancelTransaction();
      $Controller->loge(__CLASS__, __METHOD__, __LINE__, 'Unable to add unit: ' . $stmt->error, [$name]);
      return null;
    }
    $unit = new Unit([
      'unit_id' => $stmt->insert_id,
      'unit_name' => $name,
    ]);
    self::$Units[$stmt->insert_id] = $unit;
    return self::$Units[$stmt->insert_id];
  }

  public static function get(int|string $id): ?self
  {
    if (is_int($id))
      return self::$Units[$id];
    foreach (self::$Units as $key => $value) {
      if (strtolower($value->getName()) === strtolower($id))
        return self::$Units[$key];
    }
    return null;
  }

  public function getId(): int
  {
    return $this->unit_id;
  }

  public function getName(): string
  {
    return $this->unit_name;
  }

  public static function has(int $id): bool
  {
    return array_key_exists($id, self::$Units);
  }

  public function hasId(): bool
  {
    return !is_null($this->unit_id);
  }

  public function jsonSerialize(): mixed
  {
    return [
      'id' => $this->unit_id,
      'name' => $this->unit_name,
    ];
  }

  public static function loadAll(): void
  {
    global $Controller;
    $stmt = $Controller->prepare('SELECT * FROM units');
    if (is_null($stmt) || $stmt === false) {
      $Controller->loge(__CLASS__, __METHOD__, __LINE__, 'Statement is null', ['SELECT * FROM units']);
      return;
    }
    if (!$stmt->execute()) {
      $Controller->loge(__CLASS__, __METHOD__, __LINE__, 'Failed executing statement', ['SELECT * FROM units', $stmt->error, $stmt->errno]);
      return;
    }
    $result = $stmt->get_result();
    while ($record = $result->fetch_object(self::class)) {
      self::$Units[$record->getId()] = $record;
    }
  }
}

Unit::loadAll();