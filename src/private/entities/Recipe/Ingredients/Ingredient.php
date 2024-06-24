<?php

namespace Surcouf\Cookbook\Recipe\Ingredients;

use JsonSerializable;
use Surcouf\Cookbook\Recipe\Ingredients\Units\Unit;

if (!defined('CORE2'))
  exit;

class Ingredient implements JsonSerializable
{

  protected string|int $ingredient_id, $recipe_id, $sortindex;
  protected string|int|null $step_id, $unit_id;
  protected string|float|null $ingredient_quantity;
  protected string $ingredient_description;
  protected null|string $ingredient_description_de;
  protected null|string $ingredient_description_en;
  public bool $localized = false;

  public function __construct(?array $record = null)
  {
    if (!is_null($record)) {
      $this->ingredient_id = intval($record['ingredient_id']);
      $this->recipe_id = intval($record['recipe_id']);
      $this->step_id = (!is_null($record['step_id']) ? intval($record['step_id']) : null);
      $this->sortindex = intval($record['sortindex']);
      $this->unit_id = (!is_null($record['unit_id']) ? intval($record['unit_id']) : null);
      $this->ingredient_quantity = (!is_null($record['ingredient_quantity']) ? floatval($record['ingredient_quantity']) : null);
      $this->ingredient_description = $record['ingredient_description'];
    } else {
      $this->ingredient_id = intval($this->ingredient_id);
      $this->recipe_id = intval($this->recipe_id);
      $this->step_id = (!is_null($this->step_id) ? intval($this->step_id) : null);
      $this->sortindex = intval($this->sortindex);
      $this->unit_id = (!is_null($this->unit_id) ? intval($this->unit_id) : null);
      $this->ingredient_quantity = (!is_null($this->ingredient_quantity) ? floatval($this->ingredient_quantity) : null);
    }
  }

  public function getDescription(string $locale): string
  {
    return !$this->localized ? $this->ingredient_description : ($locale == 'de' && !is_null($this->ingredient_description_de) ? $this->ingredient_description_de : ($locale == 'en' && !is_null($this->ingredient_description_en) ? $this->ingredient_description_en : $this->ingredient_description));
  }

  public function getId(): int
  {
    return $this->ingredient_id;
  }

  public function getQuantity(): ?float
  {
    return $this->ingredient_quantity;
  }

  public function getRecipeId(): int
  {
    return $this->recipe_id;
  }

  public function getSortIndex(): int
  {
    return $this->sortindex;
  }

  public function getStepId(): ?int
  {
    return $this->step_id;
  }

  public function getUnit(): ?Unit
  {
    global $Controller;
    return Unit::get($this->unit_id);
  }

  public function getUnitId(): ?int
  {
    return $this->unit_id;
  }

  public function jsonForLocalization(): array
  {
    return [
      'id' => $this->ingredient_id,
      'description' => $this->ingredient_description,
      'description_de' => '',
      'description_en' => '',
    ];
  }

  public function jsonSerialize(): mixed
  {
    global $Controller;
    $locale = $Controller->userLocale();
    return [
      'description' => $this->getDescription($locale),
      'id' => $this->ingredient_id,
      'quantity' => $this->ingredient_quantity,
      'quantityCalc' => $this->ingredient_quantity,
      'unit' => (!is_null($this->unit_id) ? $this->getUnit() : ['id' => 0, 'name' => '']),
    ];
  }

  public function updateLocalization(array $localizedObject): bool
  {
    global $Controller;

    $query = 'UPDATE recipe_ingredients SET ingredient_description_de = ?, ingredient_description_en = ? WHERE ingredient_id = ?';

    $description_de = trim('' . $localizedObject['description_de']);
    $description_en = trim('' . $localizedObject['description_en']);

    $stmt = $Controller->prepare($query);
    $stmt->bind_param('ssi', $description_de, $description_en, $this->ingredient_id);
    if (!$stmt->execute()) {
      $Controller->cancelTransaction();
      $Controller->loge(__CLASS__, __METHOD__, __LINE__, $stmt->error, [$this->recipe_id, $description_de, $description_en]);
      return false;
    }

    $this->ingredient_description_de = $description_de;
    $this->ingredient_description_en = $description_en;

    return true;
  }

}