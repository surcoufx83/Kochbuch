<?php

namespace Surcouf\Cookbook\Recipe\Cooking;

use JsonSerializable;

if (!defined('CORE2'))
  exit;

class CookingStep implements JsonSerializable
{

  protected string|int $step_id, $recipe_id, $step_no;
  protected string|int|null $step_time_preparation, $step_time_cooking, $step_time_chill;
  protected string $step_title, $step_data;
  protected null|string $step_title_de, $step_data_de;
  protected null|string $step_title_en, $step_data_en;
  public bool $localized = false;

  public array $ingredients = [];

  public function __construct(?array $record = null)
  {
    if (!is_null($record)) {
      $this->step_id = intval($record['step_id']);
      $this->recipe_id = intval($record['recipe_id']);
      $this->step_no = intval($record['step_no']);
      $this->step_title = $record['step_title'];
      $this->step_data = $record['step_data'];
      $this->step_time_preparation = (!is_null($record['step_time_preparation']) ? intval($record['step_time_preparation']) : -1);
      $this->step_time_cooking = (!is_null($record['step_time_cooking']) ? intval($record['step_time_cooking']) : -1);
      $this->step_time_chill = (!is_null($record['step_time_chill']) ? intval($record['step_time_chill']) : -1);
    } else {
      $this->step_id = intval($this->step_id);
      $this->recipe_id = intval($this->recipe_id);
      $this->step_no = intval($this->step_no);
      $this->step_time_preparation = (!is_null($this->step_time_preparation) ? intval($this->step_time_preparation) : -1);
      $this->step_time_cooking = (!is_null($this->step_time_cooking) ? intval($this->step_time_cooking) : -1);
      $this->step_time_chill = (!is_null($this->step_time_chill) ? intval($this->step_time_chill) : -1);
    }
  }

  public function getContent(string $locale): string
  {
    return !$this->localized ? $this->step_data : ($locale == 'de' && !is_null($this->step_data_de) ? $this->step_data_de : ($locale == 'en' && !is_null($this->step_data_en) ? $this->step_data_en : $this->step_data));
  }

  public function getChillTime(): ?int
  {
    return $this->step_time_chill;
  }

  public function getCookingTime(): ?int
  {
    return $this->step_time_cooking;
  }

  public function getId(): int
  {
    return $this->step_id;
  }

  public function getIndex(): int
  {
    return ($this->step_no - 1);
  }

  public function getPreparationTime(): ?int
  {
    return $this->step_time_preparation;
  }

  public function getRecipeId(): int
  {
    return $this->recipe_id;
  }

  public function getStepNo(): int
  {
    return $this->step_no;
  }

  public function getTotalTime(): ?int
  {
    if ($this->step_time_cooking == -1 && $this->step_time_preparation == -1 && $this->step_time_chill == -1)
      return null;
    $val = 0;
    if ($this->step_time_cooking > -1)
      $val += $this->step_time_cooking;
    if ($this->step_time_preparation > -1)
      $val += $this->step_time_preparation;
    if ($this->step_time_chill > -1)
      $val += $this->step_time_chill;
    return $val;
  }

  public function getTitle(string $locale): string
  {
    return !$this->localized ? $this->step_title : ($locale == 'de' && !is_null($this->step_title_de) ? $this->step_title_de : ($locale == 'en' && !is_null($this->step_title_en) ? $this->step_title_en : $this->step_title));
  }

  public function jsonForLocalization(): array
  {
    $ingredients = [];
    foreach ($this->ingredients as $key => $value) {
      $ingredients[] = $value->jsonForLocalization();
    }
    return [
      'id' => $this->step_id,
      'title' => $this->step_title,
      'instructions' => $this->step_data,
      'title_de' => '',
      'title_en' => '',
      'instructions_de' => '',
      'instructions_en' => '',
      'ingredients' => $ingredients,
    ];
  }

  public function jsonSerialize(): mixed
  {
    global $Controller;
    $locale = $Controller->userLocale();
    return [
      'index' => $this->step_no,
      'ingredients' => array_values($this->ingredients),
      'name' => $this->getTitle($locale),
      'userContent' => $this->getContent($locale),
      'timeConsumed' => [
        'cooking' => ($this->step_time_cooking == -1 ? null : $this->step_time_cooking),
        'preparing' => ($this->step_time_preparation == -1 ? null : $this->step_time_preparation),
        'rest' => ($this->step_time_chill == -1 ? null : $this->step_time_chill),
        'total' => (is_null($this->getTotalTime()) ? null : $this->getTotalTime()),
        'unit' => 'minutes',
      ]
    ];
  }

  public static function load(?int $id): ?self
  {
    if (is_null($id))
      return null;
    global $Controller;
    $stmt = $Controller->prepare('SELECT * FROM recipe_steps WHERE step_id = ?');
    $stmt->bind_param('i', $id);
    if (!$stmt->execute())
      return null;
    return $stmt->get_result()->fetch_object(self::class);
  }

  public function updateLocalization(array $localizedObject): bool
  {
    global $Controller;

    $query = 'UPDATE recipe_steps SET step_title_de = ?, step_title_en = ?, step_data_de = ?, step_data_en = ? WHERE step_id = ?';

    $title_de = trim('' . $localizedObject['title_de']);
    $title_en = trim('' . $localizedObject['title_en']);
    $instructions_de = trim('' . $localizedObject['instructions_de']);
    $instructions_en = trim('' . $localizedObject['instructions_en']);

    $stmt = $Controller->prepare($query);
    $stmt->bind_param('ssssi', $title_de, $title_en, $instructions_de, $instructions_en, $this->step_id);
    if (!$stmt->execute()) {
      $Controller->cancelTransaction();
      $Controller->loge(__CLASS__, __METHOD__, __LINE__, $stmt->error, [$this->recipe_id, $title_de, $title_en, $instructions_de, $instructions_en]);
      return false;
    }

    $this->step_title_de = $title_de;
    $this->step_title_en = $title_en;
    $this->step_data_de = $instructions_de;
    $this->step_data_en = $instructions_en;

    if (count($localizedObject['ingredients']) == 0)
      return true;

    foreach ($this->ingredients as $key => $value) {
      for ($i = 0; $i < count($localizedObject['ingredients']); $i++) {
        if ($value->getId() === intval($localizedObject['ingredients'][$i]['id'])) {
          if (!$value->updateLocalization($localizedObject['ingredients'][$i])) {
            return false;
          }
        }
      }
    }

    return true;
  }

}
