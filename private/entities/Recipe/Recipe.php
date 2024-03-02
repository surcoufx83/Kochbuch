<?php

namespace Surcouf\Cookbook\Recipe;

use DateInterval;
use \DateTime;
use DateTimeZone;
use JsonSerializable;
use Surcouf\Cookbook\Recipe\Cooking\CookingStep;
use Surcouf\Cookbook\Recipe\Ingredients\Ingredient;
use Surcouf\Cookbook\Recipe\Ingredients\Units\Unit;
use Surcouf\Cookbook\Recipe\Pictures\Picture;
use Surcouf\Cookbook\User\User;

if (!defined('CORE2'))
  exit;

class Recipe implements JsonSerializable
{

  protected string|int $recipe_id, $user_id, $recipe_eater, $views = 0, $cooked = 0, $votesum = 0, $votes = 0, $ratesum = 0, $ratings = 0, $stepscount = 0, $ingredientsGroupByStep;
  protected string|float $avgvotes = 0.0, $avgratings = 0.0;
  protected string|bool $recipe_placeholder, $aigenerated, $localized, $recipe_public_internal, $recipe_public_external;
  protected string|DateTime $recipe_created, $recipe_modified;
  protected string $recipe_name, $recipe_description;
  protected null|string $recipe_name_de, $recipe_description_de;
  protected null|string $recipe_name_en, $recipe_description_en;

  protected null|string|int $edit_user_id, $picture_id, $picture_sortindex, $picture_width, $picture_height, $preparationtime = 0, $cookingtime = 0, $chilltime = 0;
  protected null|string|DateTime $recipe_edited, $recipe_published, $picture_uploaded;
  protected null|string $picture_name, $picture_description, $picture_hash, $picture_filename, $picture_full_path, $recipe_source_desc, $recipe_source_url;

  private $myvotes = null;
  protected array $ingredients = array();
  protected array $pictures = array();
  protected array $steps = array();
  protected array $categories = array();

  public function __construct()
  {
    $this->recipe_id = intval($this->recipe_id);
    $this->user_id = !is_null($this->user_id) ? intval($this->user_id) : null;
    $this->edit_user_id = !is_null($this->edit_user_id) ? intval($this->edit_user_id) : null;
    $this->aigenerated = intval($this->aigenerated) === 1;
    $this->recipe_placeholder = intval($this->recipe_placeholder) === 1;
    $this->recipe_public_internal = intval($this->recipe_public_internal) === 1;
    $this->recipe_public_external = intval($this->recipe_public_external) === 1;
    $this->recipe_eater = intval($this->recipe_eater);
    $this->recipe_created = new DateTime($this->recipe_created);
    $this->recipe_edited = !is_null($this->recipe_edited) ? new DateTime($this->recipe_edited) : null;
    $this->recipe_modified = new DateTime($this->recipe_modified);
    $this->recipe_published = !is_null($this->recipe_published) ? new DateTime($this->recipe_published) : null;
    $this->views = intval($this->views);
    $this->cooked = intval($this->cooked);
    $this->votesum = intval($this->votesum);
    $this->votes = intval($this->votes);
    $this->avgvotes = floatval($this->avgvotes);
    $this->ratesum = intval($this->ratesum);
    $this->ratings = intval($this->ratings);
    $this->avgratings = floatval($this->avgratings);
    $this->stepscount = intval($this->stepscount);
    $this->preparationtime = intval($this->preparationtime);
    $this->cookingtime = intval($this->cookingtime);
    $this->chilltime = intval($this->chilltime);
    $this->ingredientsGroupByStep = (intval($this->ingredientsGroupByStep) == 1);
    if (property_exists($this, 'picture_id')) {
      if (!is_null($this->picture_id)) {
        $picture = new Picture([
          'picture_id' => $this->picture_id,
          'recipe_id' => $this->recipe_id,
          'user_id' => null,
          'picture_sortindex' => $this->picture_sortindex,
          'picture_name' => $this->picture_name,
          'picture_description' => $this->picture_description,
          'picture_hash' => $this->picture_hash,
          'picture_filename' => $this->picture_filename,
          'picture_full_path' => $this->picture_full_path,
          'picture_uploaded' => $this->picture_uploaded,
          'picture_width' => $this->picture_width,
          'picture_height' => $this->picture_height,
        ]);
        $this->pictures[$picture->getIndex()] = $picture;
      } /* else
$this->pictures[0] = new DummyPicture(0); */
    }
  }

  public function createPicture(array &$response, array $file): bool
  {
    global $Controller;
    $picture = Picture::create($this->recipe_id, count($this->pictures), $file);
    if (!$picture->moveToPublicFolder()) {
      $response = [
        'errorCode' => 7,
        'recipe' => null,
        'userError' => false,
      ];
      return false;
    }
    if (!$picture->save($response))
      return false;
    $this->pictures[$picture->getIndex()] = $picture;
    return true;
  }

  public static function createFromAiScanner(array $aiobj): ?self
  {
    global $Controller;
    $id = null;

    file_put_contents(DIR_PUBLIC_IMAGES . DS . 'tempimages' . DS . 'ai.json', json_encode($aiobj));

    $query = 'INSERT INTO recipes(user_id, recipe_placeholder, recipe_name, recipe_description, recipe_eater, aigenerated) VALUES(?, 0, ?, ?, ?, 1)';
    $userid = $Controller->User()->getId();
    $name = array_key_exists('name', $aiobj) ? $aiobj['name'] : '';
    $summary = array_key_exists('summary', $aiobj) ? $aiobj['summary'] : '';
    $servings = array_key_exists('servings', $aiobj) && is_numeric($aiobj['servings']) ? intval($aiobj['servings']) : 4;
    $stmt = $Controller->prepare($query);
    $stmt->bind_param('issi', $userid, $name, $summary, $servings);
    if (!$stmt->execute()) {
      $Controller->loge(__CLASS__, __METHOD__, __LINE__, $stmt->error, [$query, $userid]);
      return null;
    }
    $id = $stmt->insert_id;

    $stepquery = 'INSERT INTO recipe_steps(recipe_id, step_no, step_title, step_data) VALUES (?, ?, ?, ?)';
    $ingredientquery = 'INSERT INTO recipe_ingredients(recipe_id, step_id, sortindex, unit_id, ingredient_quantity, ingredient_description) VALUES (?, ?, ?, ?, ?, ?)';
    if (array_key_exists('preparation', $aiobj) && is_array($aiobj['preparation']) && count($aiobj['preparation']) > 0) {
      if (array_key_exists('tips', $aiobj) && $aiobj['tips'] != '') {
        $aiobj['preparation'][] = [
          'ingredients' => [],
          'instructions' => is_array($aiobj['tips']) ? join("\r\n", $aiobj['tips']) : $aiobj['tips'],
          'title' => 'Tipp',
        ];
      }
      for ($i = 0; $i < count($aiobj['preparation']); $i++) {
        $step = $aiobj['preparation'][$i];
        $stmt = $Controller->prepare($stepquery);
        $steptitle = array_key_exists('title', $step) ? $step['title'] : '';
        $stmt->bind_param('iiss', $id, $i, $steptitle, $step['instructions']);
        if (!$stmt->execute()) {
          $Controller->loge(__CLASS__, __METHOD__, __LINE__, $stmt->error, [$query, $userid]);
          continue;
        }
        $stepid = $stmt->insert_id;
        if (array_key_exists('ingredients', $step) && is_array($step['ingredients'])) {
          for ($j = 0; $j < count($step['ingredients']); $j++) {
            $ingredient = $step['ingredients'][$j];
            $stepsortindex = $i * 100 + $j;
            $unitid = null;
            if (!is_null($ingredient['unit']) && $ingredient['unit'] != '') {
              $unit = Unit::get($ingredient['unit']);
              if (!is_null($unit))
                $unitid = $unit->getId();
              else {
                $unit = Unit::createUnit($ingredient['unit']);
                if (!is_null($unit))
                  $unitid = $unit->getId();
              }
            }
            $quantity = array_key_exists('quantity', $ingredient) && is_numeric($ingredient['quantity']) ? intval($ingredient['quantity']) : null;
            $name = array_key_exists('name', $ingredient) ? $ingredient['name'] : '';
            $stmt = $Controller->prepare($ingredientquery);
            $stmt->bind_param('iiiiss', $id, $stepid, $stepsortindex, $unitid, $quantity, $name);
            if (!$stmt->execute()) {
              $Controller->loge(__CLASS__, __METHOD__, __LINE__, $stmt->error, [$query, $userid]);
              continue;
            }
          }
        }
      }
    }

    $recipe = self::load($id);
    $recipe->loadComplete();
    return $recipe;
  }

  public static function createPlaceholder(): ?self
  {
    global $Controller;
    $userid = $Controller->User()->getId();
    $id = null;

    $query = 'SELECT recipe_id FROM allrecipes WHERE recipe_placeholder = 1 AND user_id = ? AND recipe_edited IS NULL';
    $stmt = $Controller->prepare($query);
    $stmt->bind_param('i', $userid);
    if (!$stmt->execute()) {
      $Controller->loge(__CLASS__, __METHOD__, __LINE__, $stmt->error, [$query, $userid]);
      return null;
    }
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
      $id = $result->fetch_assoc()['recipe_id'];
    }

    if (is_null($id)) {
      $query = 'INSERT INTO recipes(user_id, recipe_placeholder, recipe_name, recipe_description, recipe_eater) VALUES(?, 1, \'\', \'\', 4)';
      $stmt = $Controller->prepare($query);
      $stmt->bind_param('i', $userid);
      if (!$stmt->execute()) {
        $Controller->loge(__CLASS__, __METHOD__, __LINE__, $stmt->error, [$query, $userid]);
        return null;
      }
      $id = $stmt->insert_id;
    }

    $recipe = self::load($id);
    $recipe->loadComplete();
    return $recipe;
  }

  public function delete(): int
  {
    global $Controller;
    $userid = !is_null($Controller->User()) ? $Controller->User()->getId() : null;

    if (is_null($userid)) {
      $Controller->loge(__CLASS__, __METHOD__, __LINE__, 'User not loggedin', [$this->recipe_id]);
      return 400;
    }

    if ($userid !== $this->user_id && !$Controller->User()->isAdmin()) {
      $Controller->loge(__CLASS__, __METHOD__, __LINE__, 'User not owner and not admin', [$this->recipe_id, $userid]);
      return 400;
    }

    if (!$Controller->startTransaction())
      return 500;

    $query = 'DELETE FROM recipes WHERE recipe_id = ? LIMIT 1';
    $stmt = $Controller->prepare($query);
    $stmt->bind_param('i', $this->recipe_id);

    if (!$stmt->execute()) {
      $Controller->cancelTransaction();
      $Controller->loge(__CLASS__, __METHOD__, __LINE__, $stmt->error, [$this->recipe_id, $userid]);
      return 304;
    }

    $Controller->finishTransaction();
    return 202;
  }

  public function deletePicture(int $id, array &$response): bool
  {
    global $Controller;
    if (count($this->pictures) == 0) {
      $this->loadRecipePictures();
      if (count($this->pictures) == 0) {
        $response = [
          'errorCode' => 2,
          'recipe' => null,
          'userError' => false,
        ];
        $Controller->e400_BadRequest(__CLASS__, __METHOD__, __LINE__, 'No pictures at all', []);
      }
    }

    $user = !is_null($Controller->User()) ? $Controller->User() : null;

    if (is_null($user)) {
      $response = [
        'errorCode' => 3,
        'recipe' => null,
        'userError' => false,
      ];
      $Controller->e403_Forbidden(__CLASS__, __METHOD__, __LINE__, 'User not loggedin', []);
    }

    foreach ($this->pictures as $i => $picture) {
      if ($picture->getId() == $id) {

        if (!$user->isAdmin() && $user->getId() != $this->user_id && $user->getId() != $picture->getUserId()) {
          $response = [
            'errorCode' => 4,
            'recipe' => null,
            'userError' => false,
          ];
          $Controller->e403_Forbidden(__CLASS__, __METHOD__, __LINE__, 'User not allowed to delete picture', [$this->getId(), $user->getId()]);
        }

        $pathPicture = $picture->getFullpath();
        $pathThumbnail = $picture->getFullpath(true);
        $stmt = $Controller->prepare('DELETE FROM recipe_pictures WHERE picture_id = ?');
        $pictureid = $picture->getId();
        $stmt->bind_param('i', $pictureid);
        if (!$stmt->execute()) {
          $Controller->cancelTransaction();
          $response = [
            'errorCode' => 5,
            'recipe' => null,
            'userError' => false,
          ];
          $Controller->e500_ServerError(__CLASS__, __METHOD__, __LINE__, $stmt->error, [$this->getId(), $pictureid]);
        }

        $stmt = $Controller->prepare('UPDATE recipe_pictures SET picture_sortindex = picture_sortindex - 1 WHERE recipe_id = ? AND picture_sortindex > ?');
        $picturei = $picture->getIndex();
        $stmt->bind_param('ii', $this->recipe_id, $picturei);
        if (!$stmt->execute()) {
          $Controller->cancelTransaction();
          $response = [
            'errorCode' => 9,
            'recipe' => null,
            'userError' => false,
          ];
          $Controller->e500_ServerError(__CLASS__, __METHOD__, __LINE__, $stmt->error, [$this->getId(), $pictureid]);
        }

        if (!@unlink($pathPicture)) {
          $response = [
            'errorCode' => 6,
            'recipe' => null,
            'userError' => false,
          ];
          $Controller->e500_ServerError(__CLASS__, __METHOD__, __LINE__, 'Unable to unlink picture.', [$this->getId(), $pictureid, $pathPicture]);
        }
        @unlink($pathThumbnail);

        return true;
      }
    }

    $response = [
      'errorCode' => 7,
      'recipe' => null,
      'userError' => false,
    ];
    $Controller->e404_NotFound(__CLASS__, __METHOD__, __LINE__, 'No picture with matching id', [$this->getId(), $id]);
    return false;
  }

  public function deleteVote(bool $deleteCooked = false, bool $useExistingTransaction = false): int
  {
    global $Controller;
    $user = !is_null($Controller->User()) ? $Controller->User() : null;

    if (is_null($user)) {
      $Controller->loge(__CLASS__, __METHOD__, __LINE__, 'User not loggedin', []);
      return 400;
    }

    if (!$useExistingTransaction) {
      if (!$Controller->startTransaction()) {
        $Controller->loge(__CLASS__, __METHOD__, __LINE__, 'Failed starting transaction', []);
        return 500;
      }
    }

    $uid = $user->getId();

    $query = 'DELETE FROM recipe_voting_difficulty WHERE recipe_id = ? AND user_id = ?';
    $stmt = $Controller->prepare($query);
    $stmt->bind_param('ii', $this->recipe_id, $uid);
    if (!$stmt->execute()) {
      $Controller->cancelTransaction();
      $Controller->loge(__CLASS__, __METHOD__, __LINE__, $stmt->error, [$query, $this->recipe_id, $uid]);
      return 304;
    }

    $query = 'DELETE FROM recipe_voting_hearts WHERE recipe_id = ? AND user_id = ?';
    $stmt = $Controller->prepare($query);
    $stmt->bind_param('ii', $this->recipe_id, $uid);
    if (!$stmt->execute()) {
      $Controller->cancelTransaction();
      $Controller->loge(__CLASS__, __METHOD__, __LINE__, $stmt->error, [$query, $this->recipe_id, $uid]);
      return 304;
    }

    if ($deleteCooked) {
      $query = 'DELETE FROM recipe_voting_cooked WHERE recipe_id = ? AND user_id = ?';
      $stmt = $Controller->prepare($query);
      $stmt->bind_param('ii', $this->recipe_id, $uid);
      if (!$stmt->execute()) {
        $Controller->cancelTransaction();
        $Controller->loge(__CLASS__, __METHOD__, __LINE__, $stmt->error, [$query, $this->recipe_id, $uid]);
        return 304;
      }
    }

    if (!$useExistingTransaction)
      $Controller->finishTransaction();

    return 202;
  }

  public function getDescription(string $locale): string
  {
    return !$this->localized ? $this->recipe_description : ($locale == 'de' && !is_null($this->recipe_description_de) ? $this->recipe_description_de : ($locale == 'en' && !is_null($this->recipe_description_en) ? $this->recipe_description_en : $this->recipe_description));
  }

  public function getEaterCount(): int
  {
    return $this->recipe_eater;
  }

  public function getEditorUser(): ?User
  {
    global $Controller;
    return User::load($this->edit_user_id);
  }

  public function getId(): int
  {
    return $this->recipe_id;
  }

  public function getIngredients(): array
  {
    return $this->ingredients;
  }

  public function getIngredientsCount(): int
  {
    return count($this->ingredients);
  }

  public function getName(string $locale): string
  {
    return !$this->localized ? $this->recipe_name : ($locale == 'de' && !is_null($this->recipe_name_de) ? $this->recipe_name_de : ($locale == 'en' && !is_null($this->recipe_name_en) ? $this->recipe_name_en : $this->recipe_name));
  }

  public function getPictures(): array
  {
    return $this->pictures;
  }

  public function getPictureCount(): int
  {
    return count($this->pictures);
  }

  public function getPublishedDate(): ?DateTime
  {
    return $this->recipe_published;
  }

  public function getSourceDescription(): string
  {
    if (is_null($this->recipe_source_desc) || $this->recipe_source_desc == '') {
      if (is_null($this->recipe_source_url) || $this->recipe_source_url == '')
        return '';
      $pi = parse_url($this->recipe_source_url);
      return $pi['host'];
    }
    return $this->recipe_source_desc;
  }

  public function getSourceUrl(): string
  {
    return $this->recipe_source_url;
  }

  public function getSteps(): array
  {
    return $this->steps;
  }

  public function getStepsCount(): int
  {
    return $this->stepscount;
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

  public function isPlaceholder(): bool
  {
    return $this->recipe_placeholder;
  }

  public function isExternalPublished(): bool
  {
    return $this->recipe_public_external;
  }

  public function isInternalPublished(): bool
  {
    return $this->recipe_public_internal;
  }

  public function jsonForLocalization(): array
  {
    $preparation = [];
    foreach ($this->steps as $key => $value) {
      $preparation[] = $value->jsonForLocalization();
    }
    $ingredients = [];
    foreach ($this->ingredients as $key => $value) {
      $ingredients[] = $value->jsonForLocalization();
    }
    return [
      'id' => $this->recipe_id,
      'name' => $this->recipe_name,
      'name_de' => '',
      'name_en' => '',
      'summary' => $this->recipe_description,
      'summary_de' => '',
      'summary_en' => '',
      'preparation' => $preparation,
      'ingredients' => $ingredients,
    ];
  }

  public function jsonSerialize(): mixed
  {
    global $Controller;
    $locale = $Controller->userLocale();
    return [
      'id' => $this->recipe_id,
      'name' => $this->getName($locale),
      'aigenerated' => $this->aigenerated,
      'categories' => $this->categories,
      'created' => $this->recipe_created->format('c'),
      'description' => $this->getDescription($locale),
      'eaterCount' => $this->recipe_eater,
      'lastEdit' => [
        'user' => (!is_null($this->getEditorUser()) ? $this->getEditorUser()->getJsonObj() : null),
        'when' => ($this->recipe_edited ? $this->recipe_edited->format('c') : null),
      ],
      'owner' => (!is_null($this->getUser()) ? $this->getUser()->getJsonObj() : null),
      'source' => [
        'description' => $this->getSourceDescription(),
        'url' => $this->getSourceUrl(),
      ],
      'pictures' => array_values($this->pictures),
      'placeholder' => $this->recipe_placeholder,
      'preparation' => [
        'ingredients' => array_values($this->ingredients),
        'ingredientsGrouping' => ($this->ingredientsGroupByStep == false ? 'None' : 'Steps'),
        'steps' => array_values($this->steps),
        'stepscount' => $this->stepscount,
        'timeConsumed' => [
          'cooking' => $this->cookingtime,
          'preparing' => $this->preparationtime,
          'rest' => $this->chilltime,
          'total' => $this->cookingtime + $this->preparationtime + $this->chilltime,
          'unit' => 'minutes',
        ]
      ],
      'socials' => [
        'cooked' => $this->cooked,
        'views' => $this->views,
        'sharing' => [
          'links' => [],
          'publication' => [
            'isPublished' => [
              'internal' => $this->recipe_public_internal == 1 && $this->recipe_public_external != 1,
              'external' => $this->recipe_public_external == 1,
            ],
            'when' => (($this->recipe_public_internal || $this->recipe_public_external) && !is_null($this->recipe_published)) ? $this->recipe_published->format('c') : null,
          ],
        ],
        'rating' => [
          'ratings' => $this->ratings,
          'sum' => $this->ratesum,
          'avg' => $this->avgratings != 0.0 ? $this->avgratings : null,
        ],
        'voting' => [
          'votes' => $this->votes,
          'sum' => $this->votesum,
          'avg' => $this->avgvotes != 0.0 ? $this->avgvotes : null,
        ],
        'myvotes' => $this->myvotes,
      ],
    ];
  }

  public function hasPictures(): bool
  {
    return count($this->pictures) > 0;
  }

  public static function load(?int $id): ?self
  {
    if (is_null($id))
      return null;
    global $Controller;
    $stmt = $Controller->prepare('SELECT * FROM allrecipes WHERE recipe_id = ?');
    $stmt->bind_param('i', $id);
    if (!$stmt->execute())
      return null;
    $recipe = $stmt->get_result()->fetch_object(self::class);
    if (is_null($recipe))
      return null;
    if ($recipe->isPlaceholder()) {
      if ($Controller->User()->getId() !== $recipe->getUserId() && !$Controller->User()->isAdmin())
        return null;
    }
    return $recipe;
  }

  public function loadComplete(): void
  {
    global $Controller;
    $this
      ->loadRecipeSteps()
      ->loadRecipeIngredients()
      ->loadRecipePictures()
      ->loadRecipeCategories()
      ->loadMySocialProperties();
    /* if ($this->ingredientsGroupByStep == true) {
      foreach ($this->steps as $stepKey => $step) {
        foreach ($this->ingredients as $ingKey => $ing) {
          if ($step->getId() == $ing->getStepId())
            $this->steps[$stepKey]->ingredients[$ingKey] = $ing;
        }
      }
    } */
  }

  public function loadForListings(): void
  {
    global $Controller;
    $this->loadRecipeCategories();
  }

  /**
   * Load social properties of a user who is not the owner of the recipe.
   * If one is the owner, this method will do nothing.
   */
  private function loadMySocialProperties(): self
  {
    global $Controller;
    if (is_null($Controller->User()))
      return $this;
    if ($this->recipe_id === $Controller->User()->getId())
      return $this;
    $this->myvotes = [
      'cooked' => $this->loadMyCooked($Controller->User()->getId()),
      'rating' => $this->loadMyRatings($Controller->User()->getId()),
      'voting' => $this->loadMyVotes($Controller->User()->getId()),
    ];
    return $this;
  }

  private function loadMyCooked(int $userid): array
  {
    global $Controller;
    $value = [];
    $stmt = $Controller->prepare('SELECT * FROM recipe_voting_cooked WHERE recipe_id = ? AND user_id = ?');
    $stmt->bind_param('ii', $this->recipe_id, $userid);
    if (!$stmt->execute())
      return $value;
    $result = $stmt->get_result();
    if ($result->num_rows == 0)
      return $value;
    while ($record = $result->fetch_assoc()) {
      $value[] = [
        'cooked' => true,
        'when' => (new DateTime($record['when'], new DateTimeZone('UTC')))->format('c'),
      ];
    }
    return $value;
  }

  private function loadMyRatings(int $userid): array
  {
    global $Controller;
    $value = [
      'voted' => false,
      'value' => 0,
      'when' => null
    ];
    $stmt = $Controller->prepare('SELECT * FROM recipe_voting_difficulty WHERE recipe_id = ? AND user_id = ?');
    $stmt->bind_param('ii', $this->recipe_id, $userid);
    if (!$stmt->execute())
      return $value;
    $result = $stmt->get_result();
    if ($result->num_rows == 0)
      return $value;
    $result = $result->fetch_assoc();
    return [
      'voted' => true,
      'value' => intval($result['value']),
      'when' => (new DateTime($result['when'], new DateTimeZone('UTC')))->format('c'),
    ];
  }

  private function loadMyVotes(int $userid): array
  {
    global $Controller;
    $value = [
      'voted' => false,
      'value' => 0,
      'when' => null
    ];
    $stmt = $Controller->prepare('SELECT * FROM recipe_voting_hearts WHERE recipe_id = ? AND user_id = ?');
    $stmt->bind_param('ii', $this->recipe_id, $userid);
    if (!$stmt->execute())
      return $value;
    $result = $stmt->get_result();
    if ($result->num_rows == 0)
      return $value;
    $result = $result->fetch_assoc();
    return [
      'voted' => true,
      'value' => intval($result['value']),
      'when' => (new DateTime($result['when'], new DateTimeZone('UTC')))->format('c'),
    ];
  }

  private function loadRecipeCategories(): self
  {
    global $Controller;
    $stmt = $Controller->prepare('SELECT * FROM recipecategoriesview WHERE recipe_id = ?');
    $stmt->bind_param('i', $this->recipe_id);
    if (!$stmt->execute())
      return $this;
    $result = $stmt->get_result();
    while ($record = $result->fetch_assoc()) {
      $this->categories[] = [
        'catid' => $record['catid'],
        'itemid' => $record['itemid'],
        'votes' => $record['votes'],
      ];
    }
    return $this;
  }

  private function loadRecipeIngredients(): self
  {
    global $Controller;
    $stmt = $Controller->prepare('SELECT * FROM recipe_ingredients WHERE recipe_id = ? ORDER BY step_id ASC, sortindex ASC');
    $stmt->bind_param('i', $this->recipe_id);
    if (!$stmt->execute())
      return $this;
    $result = $stmt->get_result();
    while ($record = $result->fetch_object(Ingredient::class)) {
      $record->localized = $this->localized;
      if (!is_null($record->getStepId())) {
        foreach ($this->steps as $stepKey => $step) {
          // for ($i = 0; $i < count($this->steps); $i++) {
          if ($step->getId() == $record->getStepId())
            $this->steps[$stepKey]->ingredients[] = $record;
        }
      } else {
        $this->ingredients[] = $record;
      }
    }
    return $this;
  }

  private function loadRecipePictures(int $limit = 999): self
  {
    global $Controller;
    $this->pictures = [];
    $stmt = $Controller->prepare('SELECT * FROM recipe_pictures WHERE recipe_id = ? ORDER BY picture_sortindex LIMIT ?');
    $stmt->bind_param('ii', $this->recipe_id, $limit);
    if (!$stmt->execute())
      return $this;
    $result = $stmt->get_result();
    while ($record = $result->fetch_object(Picture::class)) {
      $this->pictures[$record->getIndex()] = $record;
    }
    return $this;
  }

  private function loadRecipeSteps(): self
  {
    global $Controller;
    $stmt = $Controller->prepare('SELECT * FROM recipe_steps WHERE recipe_id = ?');
    $stmt->bind_param('i', $this->recipe_id);
    if (!$stmt->execute())
      return $this;
    $result = $stmt->get_result();
    while ($record = $result->fetch_object(CookingStep::class)) {
      $record->localized = $this->localized;
      $this->steps[$record->getIndex()] = $record;
    }
    return $this;
  }

  public function mayIEdit(): bool
  {
    global $Controller;
    $user = $Controller->User();
    return !is_null($user) && ($user->getId() === $this->getUserId() || $user->isAdmin());
  }

  public function mayIRead(): bool
  {
    global $Controller;
    $user = $Controller->User();
    if (!is_null($user) && $this->user_id == $user->getId())
      return true;
    if ($this->isExternalPublished())
      return true;
    if ($this->isInternalPublished() && !is_null($user))
      return true;
    if (!is_null($user) && $user->isAdmin())
      return true;
    return false;
  }

  public function publish(string $target): int
  {
    global $Controller;
    $userid = !is_null($Controller->User()) ? $Controller->User()->getId() : null;

    if (is_null($userid)) {
      $Controller->loge(__CLASS__, __METHOD__, __LINE__, 'User not loggedin', [$this->recipe_id]);
      return 400;
    }

    if ($userid !== $this->user_id && !$Controller->User()->isAdmin()) {
      $Controller->loge(__CLASS__, __METHOD__, __LINE__, 'User not owner and not admin', [$this->recipe_id, $userid]);
      return 400;
    }

    $currentTarget = $this->recipe_public_external ? 'external' : ($this->recipe_public_internal ? 'internal' : 'private');
    if ($currentTarget == $target) {
      $Controller->loge(__CLASS__, __METHOD__, __LINE__, 'Recipe already published', [$this->recipe_id, $target]);
      return 304;
    }

    if (!$Controller->startTransaction())
      return 500;

    $query = 'UPDATE recipes SET recipe_public_internal = ?, recipe_public_external = ?, recipe_published = ' . ($target !== 'private' ? 'CURRENT_TIMESTAMP()' : 'NULL') . ' WHERE recipe_id = ? LIMIT 1';
    $stmt = $Controller->prepare($query);
    $varint = $target === 'internal' ? 1 : 0;
    $varext = $target === 'external' ? 1 : 0;
    $stmt->bind_param('iii', $varint, $varext, $this->recipe_id);

    if (!$stmt->execute()) {
      $Controller->cancelTransaction();
      $Controller->loge(__CLASS__, __METHOD__, __LINE__, $stmt->error, [$this->recipe_id, $userid, $target]);
      return 304;
    }

    $Controller->finishTransaction();
    return 202;
  }

  public function update(array $recipearray, int &$messageCode, bool &$userError): int
  {
    global $Controller;

    if (!$Controller->startTransaction()) {
      $messageCode = 1;
      $userError = false;
      return 500;
    }

    if (!$this->updateBase($recipearray, $messageCode, $userError))
      return 400;

    if (!$this->updateIngredients($recipearray, $messageCode, $userError))
      return 400;

    if (!$this->updateSteps($recipearray, $messageCode, $userError))
      return 400;

    if (!$Controller->finishTransaction()) {
      $messageCode = 2;
      $userError = false;
      return 500;
    }

    return 202;
  }

  private function updateBase(array $recipearray, int &$messageCode, bool &$userError): bool
  {
    global $Controller;
    $query = 'UPDATE recipes SET recipe_name = ?, recipe_description = ?, recipe_eater = ?, recipe_source_desc = ?,'
      . ' recipe_source_url = ?, ingredientsGroupByStep = ?, edit_user_id = ?, recipe_placeholder = 0,'
      . ' recipe_edited = CURRENT_TIMESTAMP(), localized = 0 WHERE recipe_id = ?';

    $name = trim('' . $recipearray['name']);
    if (strlen($name) < 3 || strlen($name) > 256) {
      $Controller->cancelTransaction();
      $Controller->loge(__CLASS__, __METHOD__, __LINE__, 'Invalid recipe name length', [$this->recipe_id, $name]);
      $messageCode = strlen($name) < 3 ? 10 : 11;
      $userError = true;
      return false;
    }

    $description = trim('' . $recipearray['description']);
    if (strlen($description) > 1024) {
      $Controller->cancelTransaction();
      $Controller->loge(__CLASS__, __METHOD__, __LINE__, 'Invalid recipe description length', [$this->recipe_id, $description]);
      $messageCode = 12;
      $userError = true;
      return false;
    }

    $eater = intval($recipearray['eaterCount']);
    if ($eater <= 0 || $eater > 255) {
      $Controller->cancelTransaction();
      $Controller->loge(__CLASS__, __METHOD__, __LINE__, 'Invalid recipe eater count', [$this->recipe_id, $eater]);
      $messageCode = $eater <= 0 ? 13 : 14;
      $userError = true;
      return false;
    }

    $source_desc = trim('' . $recipearray['source']['description']);
    if (strlen($source_desc) > 1024) {
      $Controller->cancelTransaction();
      $Controller->loge(__CLASS__, __METHOD__, __LINE__, 'Invalid recipe source description length', [$this->recipe_id, $source_desc]);
      $messageCode = 15;
      $userError = true;
      return false;
    }

    $source_url = trim('' . $recipearray['source']['url']);
    if (strlen($source_url) > 256) {
      $Controller->cancelTransaction();
      $Controller->loge(__CLASS__, __METHOD__, __LINE__, 'Invalid recipe source url length', [$this->recipe_id, $source_url]);
      $messageCode = 16;
      $userError = true;
      return false;
    }

    $groupByStep = $recipearray['preparation']['ingredientsGrouping'] == 'Steps' ? 1 : 0;

    $editUserId = $Controller->User()->getId();

    $stmt = $Controller->prepare($query);
    $stmt->bind_param('ssissiii', $name, $description, $eater, $source_desc, $source_url, $groupByStep, $editUserId, $this->recipe_id);
    if (!$stmt->execute()) {
      $Controller->cancelTransaction();
      $Controller->loge(__CLASS__, __METHOD__, __LINE__, $stmt->error, [$this->recipe_id, $name, $description, $eater, $source_desc, $source_url, $groupByStep, $editUserId]);
      $messageCode = 3;
      $userError = false;
      return false;
    }

    $this->recipe_name = $name;
    $this->recipe_description = $description;
    $this->recipe_eater = $eater;
    $this->recipe_source_desc = $source_desc;
    $this->recipe_source_url = $source_url;
    $this->ingredientsGroupByStep = $groupByStep;

    return true;
  }

  private function updateIngredients(array $recipearray, int &$messageCode, bool &$userError): bool
  {
    global $Controller;

    if (is_null($recipearray['preparation']) || is_null($recipearray['preparation']['ingredients']) || is_null($recipearray['preparation']['steps'])) {
      $Controller->cancelTransaction();
      $Controller->loge(__CLASS__, __METHOD__, __LINE__, 'Preparation, Ingredients or Steps is null.', [$recipearray]);
      $messageCode = 4;
      $userError = false;
      return false;
    }

    $groupByStep = $recipearray['preparation']['ingredientsGrouping'] == 'Steps' ? 1 : 0;

    $query = 'DELETE FROM recipe_ingredients WHERE recipe_id = ?';
    $stmt = $Controller->prepare($query);
    $stmt->bind_param('i', $this->recipe_id);
    if (!$stmt->execute()) {
      $Controller->cancelTransaction();
      $Controller->loge(__CLASS__, __METHOD__, __LINE__, 'Unable to delete ingredients: ' . $stmt->error, [$groupByStep, $this->recipe_id]);
      $messageCode = 3;
      $userError = false;
      return false;
    }

    $this->ingredients = [];

    if (!$groupByStep)
      return $this->updateIngredientsNoGroup($recipearray, $messageCode, $userError);

    return true;
  }

  private function updateIngredientsBySteps(array $recipearray, int $stepIndex, CookingStep $step, int &$messageCode, bool &$userError): bool
  {
    global $Controller;

    if (count($recipearray['preparation']['steps']) == 0) {
      $Controller->cancelTransaction();
      $Controller->loge(__CLASS__, __METHOD__, __LINE__, 'Steps array is empty.', [$recipearray]);
      return false;
    }

    $query = 'INSERT INTO recipe_ingredients(recipe_id, step_id, sortindex, unit_id, ingredient_quantity, ingredient_description) VALUES(?, ?, ?, ?, ?, ?)';
    $valid = 0;
    for ($i = 0; $i < count($recipearray['preparation']['steps'][$stepIndex]['ingredients']); $i++) {
      $ingredient = $recipearray['preparation']['steps'][$stepIndex]['ingredients'][$i];
      $ingredient['description'] = trim($ingredient['description']);
      if ($ingredient['description'] == '')
        continue;
      $unitid = null;
      if (!is_null($ingredient['unit'])) {
        if ($ingredient['unit']['name'] != '') {
          $unit = Unit::get($ingredient['unit']['name']);
          if (!is_null($unit))
            $unitid = $unit->getId();
          else {
            $unit = Unit::createUnit($ingredient['unit']['name']);
            if (!is_null($unit))
              $unitid = $unit->getId();
          }
        }
      }
      $stepid = $step->getId();
      $stepIngredientIndex = $stepIndex * 100 + $i;
      $stmt = $Controller->prepare($query);
      $stmt->bind_param('iiiids', $this->recipe_id, $stepid, $stepIngredientIndex, $unitid, $ingredient['quantity'], $ingredient['description']);
      if (!$stmt->execute()) {
        $Controller->cancelTransaction();
        $Controller->loge(__CLASS__, __METHOD__, __LINE__, 'Unable to add ingredients: ' . $stmt->error, [$this->recipe_id, $i, $unitid, $ingredient['quantity'], $ingredient['description']]);
        $messageCode = 3;
        $userError = false;
        return false;
      }
      $valid++;
    }

    return true;
  }

  private function updateIngredientsNoGroup(array $recipearray, int &$messageCode, bool &$userError): bool
  {
    global $Controller;

    if (count($recipearray['preparation']['ingredients']) == 0) {
      $Controller->cancelTransaction();
      $Controller->loge(__CLASS__, __METHOD__, __LINE__, 'Ingredients array is empty.', [$recipearray]);
      $messageCode = 20;
      $userError = true;
      return false;
    }

    $query = 'INSERT INTO recipe_ingredients(recipe_id, sortindex, unit_id, ingredient_quantity, ingredient_description) VALUES(?, ?, ?, ?, ?)';
    $valid = 0;
    for ($i = 0; $i < count($recipearray['preparation']['ingredients']); $i++) {
      $ingredient = $recipearray['preparation']['ingredients'][$i];
      $ingredient['description'] = trim($ingredient['description']);
      if ($ingredient['description'] == '')
        continue;
      $unitid = null;
      if (!is_null($ingredient['unit'])) {
        if ($ingredient['unit']['name'] != '') {
          $unit = Unit::get($ingredient['unit']['name']);
          if (!is_null($unit))
            $unitid = $unit->getId();
          else {
            $unit = Unit::createUnit($ingredient['unit']['name']);
            if (!is_null($unit))
              $unitid = $unit->getId();
          }
        }
      }
      $stmt = $Controller->prepare($query);
      $stmt->bind_param('iiids', $this->recipe_id, $i, $unitid, $ingredient['quantity'], $ingredient['description']);
      if (!$stmt->execute()) {
        $Controller->cancelTransaction();
        $Controller->loge(__CLASS__, __METHOD__, __LINE__, 'Unable to add ingredients: ' . $stmt->error, [$this->recipe_id, $i, $unitid, $ingredient['quantity'], $ingredient['description']]);
        $messageCode = 3;
        $userError = false;
        return false;
      }
      $valid++;
    }

    if ($valid == 0) {
      $Controller->cancelTransaction();
      $Controller->loge(__CLASS__, __METHOD__, __LINE__, 'No valid ingredients added.', [$this->recipe_id]);
      $messageCode = 21;
      $userError = true;
      return false;
    }

    $this->loadRecipeIngredients();

    return true;
  }

  public function updateLocalization(array $localizedObject): bool
  {
    global $Controller;

    $query = 'UPDATE recipes SET recipe_name_de = ?, recipe_name_en = ?, recipe_description_de = ?, recipe_description_en = ?,'
      . ' localized = 1 WHERE recipe_id = ?';

    $name_de = trim('' . $localizedObject['name_de']);
    $name_en = trim('' . $localizedObject['name_en']);
    $summary_de = trim('' . $localizedObject['summary_de']);
    $summary_en = trim('' . $localizedObject['summary_en']);

    $stmt = $Controller->prepare($query);
    $stmt->bind_param('ssssi', $name_de, $name_en, $summary_de, $summary_en, $this->recipe_id);
    if (!$stmt->execute()) {
      $Controller->cancelTransaction();
      $Controller->loge(__CLASS__, __METHOD__, __LINE__, $stmt->error, [$this->recipe_id, $name_de, $name_en, $summary_de, $summary_en]);
      return false;
    }

    $this->recipe_name_de = $name_de;
    $this->recipe_name_en = $name_en;
    $this->recipe_description_de = $summary_de;
    $this->recipe_description_en = $summary_en;

    foreach ($this->steps as $key => $value) {
      for ($i = 0; $i < count($localizedObject['preparation']); $i++) {
        if ($value->getId() === intval($localizedObject['preparation'][$i]['id'])) {
          if (!$value->updateLocalization($localizedObject['preparation'][$i])) {
            return false;
          }
          break;
        }
      }
    }

    foreach ($this->ingredients as $key => $value) {
      for ($i = 0; $i < count($localizedObject['ingredients']); $i++) {
        if ($value->getId() === intval($localizedObject['ingredients'][$i]['id'])) {
          if (!$value->updateLocalization($localizedObject['ingredients'][$i])) {
            return false;
          }
          break;
        }
      }
    }

    return true;
  }

  private function updateSteps(array $recipearray, int &$messageCode, bool &$userError): bool
  {
    global $Controller;

    if (is_null($recipearray['preparation']) || is_null($recipearray['preparation']['steps'])) {
      $Controller->cancelTransaction();
      $Controller->loge(__CLASS__, __METHOD__, __LINE__, 'Preparation or Steps is null.', [$recipearray]);
      $messageCode = 4;
      $userError = false;
      return false;
    }

    $groupByStep = $recipearray['preparation']['ingredientsGrouping'] == 'Steps' ? 1 : 0;

    $query = 'DELETE FROM recipe_steps WHERE recipe_id = ?';
    $stmt = $Controller->prepare($query);
    $stmt->bind_param('i', $this->recipe_id);
    if (!$stmt->execute()) {
      $Controller->cancelTransaction();
      $Controller->loge(__CLASS__, __METHOD__, __LINE__, 'Unable to delete steps: ' . $stmt->error, [$groupByStep, $this->recipe_id]);
      $messageCode = 3;
      $userError = false;
      return false;
    }

    $this->steps = [];

    return $this->updateStepsMain($recipearray, $groupByStep, $messageCode, $userError);
  }

  private function updateStepsMain(array $recipearray, bool $groupByStep, int &$messageCode, bool &$userError): bool
  {
    global $Controller;

    if (count($recipearray['preparation']['steps']) == 0) {
      $Controller->cancelTransaction();
      $Controller->loge(__CLASS__, __METHOD__, __LINE__, 'Steps array is empty.', [$recipearray]);
      $messageCode = 20;
      $userError = true;
      return false;
    }

    $query = 'INSERT INTO recipe_steps(recipe_id, step_no, step_title, step_data, step_time_preparation, step_time_cooking, step_time_chill) VALUES(?, ?, ?, ?, ?, ?, ?)';
    $valid = 0;
    for ($i = 0; $i < count($recipearray['preparation']['steps']); $i++) {
      $step = $recipearray['preparation']['steps'][$i];
      $step['name'] = trim($step['name']);
      $step['userContent'] = trim($step['userContent']);
      $step['timeConsumed']['cooking'] = intval($step['timeConsumed']['cooking']) > 0 ? intval($step['timeConsumed']['cooking']) : null;
      $step['timeConsumed']['preparing'] = intval($step['timeConsumed']['preparing']) > 0 ? intval($step['timeConsumed']['preparing']) : null;
      $step['timeConsumed']['rest'] = intval($step['timeConsumed']['rest']) > 0 ? intval($step['timeConsumed']['rest']) : null;
      $stmt = $Controller->prepare($query);
      $stmt->bind_param('iissiii', $this->recipe_id, $i, $step['name'], $step['userContent'], $step['timeConsumed']['preparing'], $step['timeConsumed']['cooking'], $step['timeConsumed']['rest']);
      if (!$stmt->execute()) {
        $Controller->cancelTransaction();
        $Controller->loge(__CLASS__, __METHOD__, __LINE__, 'Unable to add step: ' . $stmt->error, [$this->recipe_id, $i, $step['name'], $step['userContent'], $step['timeConsumed']['preparing'], $step['timeConsumed']['cooking'], $step['timeConsumed']['rest']]);
        $messageCode = 3;
        $userError = false;
        return false;
      }
      $stepobj = CookingStep::load($stmt->insert_id);
      $this->steps[] = $stepobj;
      if ($groupByStep) {
        if (!$this->updateIngredientsBySteps($recipearray, $i, $stepobj, $messageCode, $userError))
          return false;
      }
      $valid++;
    }

    if ($valid == 0) {
      $Controller->cancelTransaction();
      $Controller->loge(__CLASS__, __METHOD__, __LINE__, 'No valid steps added.', [$this->recipe_id]);
      $messageCode = 21;
      $userError = true;
      return false;
    }

    if ($groupByStep)
      $this->loadRecipeIngredients();

    return true;
  }

  public function view(): int
  {
    global $Controller;
    $userid = !is_null($Controller->User()) ? $Controller->User()->getId() : null;
    $browserid = is_null($userid) ? $Controller->getBrowserIdentifier() : null;
    $maxage = $Controller->Config()->Page('Timespans', 'BetweenVisitCounts');
    $finddate = (new DateTime('now', new DateTimeZone('UTC')))->sub(new DateInterval($maxage))->format(DTF_SQL);

    if (!$Controller->startTransaction())
      return 500;

    if (is_null($userid)) {
      $query = 'DELETE FROM recipe_voting_views WHERE recipe_id = ? AND user_id IS NULL AND browser_id = ? AND `when` >= ?';
      $stmt = $Controller->prepare($query);
      $stmt->bind_param('iss', $this->recipe_id, $browserid, $finddate);
    } else {
      $query = 'DELETE FROM recipe_voting_views WHERE recipe_id = ? AND user_id = ? AND browser_id IS NULL AND `when` >= ?';
      $stmt = $Controller->prepare($query);
      $stmt->bind_param('iis', $this->recipe_id, $userid, $finddate);
    }

    if (!$stmt->execute()) {
      $Controller->cancelTransaction();
      $Controller->loge(__CLASS__, __METHOD__, __LINE__, $stmt->error, [$this->recipe_id, $userid, $browserid, $maxage]);
      return 304;
    }

    $stmt = $Controller->prepare('INSERT INTO recipe_voting_views(recipe_id, user_id, browser_id) VALUES(?, ?, ?)');
    $stmt->bind_param('iis', $this->recipe_id, $userid, $browserid);

    if (!$stmt->execute()) {
      $Controller->cancelTransaction();
      $Controller->loge(__CLASS__, __METHOD__, __LINE__, $stmt->error, [$this->recipe_id, $userid, $browserid, $maxage]);
      return 304;
    }

    $Controller->finishTransaction();
    return 202;
  }

  public function vote(int $cooked, int $difficulty, int $hearts): int
  {
    global $Controller;
    $user = !is_null($Controller->User()) ? $Controller->User() : null;

    if (is_null($user)) {
      $Controller->loge(__CLASS__, __METHOD__, __LINE__, 'User not loggedin', []);
      return 400;
    }

    if ($cooked == -1 && $difficulty == 0 && $hearts == 0) {
      $Controller->loge(__CLASS__, __METHOD__, __LINE__, 'Default values provided', [$cooked, $difficulty, $hearts]);
      return 304;
    }

    if ($cooked < -1 || $cooked > 1) {
      $Controller->loge(__CLASS__, __METHOD__, __LINE__, 'Invalid input value for param cooked', [$cooked]);
      return 400;
    }

    if ($difficulty < 0 || $difficulty > 3) {
      $Controller->loge(__CLASS__, __METHOD__, __LINE__, 'Invalid input value for param difficulty', [$difficulty]);
      return 400;
    }

    if ($hearts < 0 || $hearts > 5) {
      $Controller->loge(__CLASS__, __METHOD__, __LINE__, 'Invalid input value for param hearts', [$hearts]);
      return 400;
    }

    if (!$Controller->startTransaction()) {
      $Controller->loge(__CLASS__, __METHOD__, __LINE__, 'Failed starting transaction', []);
      return 500;
    }

    $deleteResult = $this->deleteVote(false, true);
    if ($deleteResult == 500) {
      $Controller->cancelTransaction();
      return 500;
    }

    $uid = $user->getId();

    if ($cooked == 1) {
      $query = 'INSERT INTO recipe_voting_cooked(recipe_id, user_id) VALUES(?, ?)';
      $stmt = $Controller->prepare($query);
      $stmt->bind_param('ii', $this->recipe_id, $uid);
      if (!$stmt->execute()) {
        $Controller->cancelTransaction();
        $Controller->loge(__CLASS__, __METHOD__, __LINE__, $stmt->error, [$query, $this->recipe_id, $uid]);
        return 500;
      }
    }

    if ($difficulty != 0) {
      $query = 'INSERT INTO recipe_voting_difficulty(recipe_id, user_id, value) VALUES(?, ?, ?)';
      $stmt = $Controller->prepare($query);
      $stmt->bind_param('iii', $this->recipe_id, $uid, $difficulty);
      if (!$stmt->execute()) {
        $Controller->cancelTransaction();
        $Controller->loge(__CLASS__, __METHOD__, __LINE__, $stmt->error, [$query, $this->recipe_id, $uid]);
        return 500;
      }
    }

    if ($hearts != 0) {
      $query = 'INSERT INTO recipe_voting_hearts(recipe_id, user_id, value) VALUES(?, ?, ?)';
      $stmt = $Controller->prepare($query);
      $stmt->bind_param('iii', $this->recipe_id, $uid, $hearts);
      if (!$stmt->execute()) {
        $Controller->cancelTransaction();
        $Controller->loge(__CLASS__, __METHOD__, __LINE__, $stmt->error, [$query, $this->recipe_id, $uid]);
        return 500;
      }
    }

    $Controller->finishTransaction();
    return 202;
  }
}