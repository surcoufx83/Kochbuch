<?php

namespace Surcouf\Cookbook\User;

use mysqli_stmt;
use Surcouf\Cookbook\Helper\ConverterHelper;
use Surcouf\Cookbook\Helper\HashHelper;
use Surcouf\Cookbook\Database\EQueryType;
use Surcouf\Cookbook\Database\QueryBuilder;
use Surcouf\Cookbook\User\Session\Session;
use \DateTime;

use League\OAuth2\Client\Token\AccessToken;

if (!defined('CORE2'))
  exit;

class User implements \JsonSerializable
{

  protected string|int $user_id;
  protected string|bool $user_isadmin, $user_isactivated, $user_betatester;
  protected null|string|DateTime $user_email_validated, $user_registration_completed, $user_adconsent, $user_email_validation, $user_last_activity;
  protected string $user_firstname, $user_lastname, $user_fullname, $initials, $user_password, $user_email;
  protected string|null $user_name, $oauth_user_name, $user_hash, $user_avatar;

  private array|null $user_statistics = null;

  private Session|null $session = null;

  public function __construct(?array $record = null)
  {
    if (!is_null($record)) {
      $this->user_id = intval($record['user_id']);
      $this->user_name = $record['user_name'];
      $this->oauth_user_name = $record['oauth_user_name'];
      $this->user_firstname = $record['user_firstname'];
      $this->user_lastname = $record['user_lastname'];
      $this->user_fullname = $record['user_fullname'];
      $this->user_hash = $record['user_hash'];
      $this->user_isadmin = (ConverterHelper::to_bool($record['user_isadmin']) && !is_null($record['user_email_validated']));
      $this->user_password = $record['user_password'];
      $this->user_email = $record['user_email'];
      $this->user_email_validation = (!is_null($record['user_email_validation']) ? $record['user_email_validation'] : '');
      $this->user_email_validated = (!is_null($record['user_email_validated']) ? new DateTime($record['user_email_validated']) : '');
      $this->user_last_activity = (!is_null($record['user_last_activity']) ? new DateTime($record['user_last_activity']) : '');
      $this->user_registration_completed = (!is_null($record['user_registration_completed']) ? new DateTime($record['user_registration_completed']) : null);
      $this->user_adconsent = (!is_null($record['user_adconsent']) ? new DateTime($record['user_adconsent']) : false);
      $this->user_betatester = intval($record['user_betatester']) === 1;
    } else {
      $this->user_id = intval($this->user_id);
      $this->user_isadmin = (ConverterHelper::to_bool($this->user_isadmin) && !is_null($this->user_email_validated));
      $this->user_email_validation = (!is_null($this->user_email_validation) ? $this->user_email_validation : '');
      $this->user_email_validated = (!is_null($this->user_email_validated) ? new DateTime($this->user_email_validated) : '');
      $this->user_last_activity = (!is_null($this->user_last_activity) ? new DateTime($this->user_last_activity) : '');
      $this->user_registration_completed = (!is_null($this->user_registration_completed) ? new DateTime($this->user_registration_completed) : null);
      $this->user_adconsent = (!is_null($this->user_adconsent) ? new DateTime($this->user_adconsent) : false);
      $this->user_betatester = intval($this->user_betatester) === 1;
    }
    if ($this->user_firstname != '' || $this->user_lastname != '')
      $this->initials = strtoupper(substr($this->user_firstname, 0, 1) . substr($this->user_lastname, 0, 1));
    else
      $this->initials = strtoupper(substr($this->getUsername(), 0, 1));
    if (is_null($this->user_hash))
      $this->calculateHash();
  }

  public function agreedToAds(): bool
  {
    return ($this->user_adconsent !== false);
  }

  public function calculateHash(): string
  {
    global $Controller;
    $data = [
      $this->user_id,
      $this->user_firstname,
      $this->user_lastname,
      $this->initials,
      $this->user_email,
    ];
    $this->user_hash = HashHelper::hash(join($data));
    $query = 'UPDATE users SET user_hash = ? WHERE user_id = ?';
    $stmt = $Controller->prepare($query);
    $stmt->bind_param('si', $this->user_hash, $this->user_id);
    if (!$stmt->execute())
      $Controller->loge(__CLASS__, __METHOD__, __LINE__, 'Failed executing Stmt: ' . $stmt->error, [$query, $this->user_id, $this->user_hash, $stmt->errno, $stmt->error]);
    return $this->user_hash;
  }

  public function createNewSession(bool $keepSession, ?AccessToken $token = null): bool
  {
    global $Controller;
    $session_token = HashHelper::generate_token(16);
    $session_password = HashHelper::generate_token(24);
    $session_password4hash = HashHelper::hash(substr($session_token, 0, 16));
    $session_password4hash .= $session_password;
    $session_password4hash .= HashHelper::hash(substr($session_token, 16));
    $hash_token = password_hash($session_token, PASSWORD_ARGON2I, ['threads' => $Controller->Config()->System('Checksums', 'PwHashThreads')]);
    $hash_password = password_hash($session_password4hash, PASSWORD_ARGON2I, ['threads' => $Controller->Config()->System('Checksums', 'PwHashThreads')]);

    if ($Controller->setSessionCookies($this->user_name, $session_token, $session_password, $keepSession)) {
      $tokenstr = (!is_null($token) ? json_encode($token->jsonSerialize()) : NULL);
      $query = new QueryBuilder(EQueryType::qtINSERT, 'user_logins');
      $query->columns(['user_id', 'login_token', 'login_password', 'login_keep', 'login_oauthdata'])
        ->values([$this->user_id, $hash_token, $hash_password, $keepSession, $tokenstr]);
      if ($Controller->insert($query)) {
        $this->session = new Session(
          $this,
          array(
            'login_id' => 0,
            'user_id' => $this->user_id,
            'login_time' => (new DateTime())->format('Y-m-d H:i:s'),
            'login_keep' => $keepSession,
            'login_oauthdata' => $tokenstr,
          )
        );
        return true;
      }
    }
    return false;
  }

  private function exposeMail(): bool
  {
    return false;
  }

  public function getFirstname(): string
  {
    return $this->user_firstname;
  }

  public function getHash(bool $calculateIfNull = true): ?string
  {
    if (is_null($this->user_hash))
      $this->calculateHash();
    return $this->user_hash;
  }

  public function getId(): int
  {
    return $this->user_id;
  }

  public function getInitials(): string
  {
    return $this->initials;
  }

  public function getJsonObj(bool $short = true): array
  {
    global $Controller;
    if (!$short && $Controller->isAuthenticated() && $Controller->User()->getId() == $this->getId())
      return $this->getJsonObj_Extended();
    return $this->getJsonObj_Simple();
  }

  private function getJsonObj_Extended(): array
  {
    global $Controller;
    $this->loadStatistics();
    return [
      'betaTester' => $this->user_betatester,
      'cloudAccount' => $this->isOAuthUser(),
      'consent' => [
        'sys2me' => [
          'message' => false,
          'email' => false,
        ],
        'user2me' => [
          'message' => false,
          'email' => false,
          'exposeMail' => $this->exposeMail(),
        ],
      ],
      'id' => $this->user_id,
      'isAdmin' => $this->isAdmin(),
      'listItemsPerPage' => $Controller->Config()->Defaults('Lists', 'Entries'),
      'loggedIn' => true,
      'meta' => [
        'email' => $this->user_email,
        'fn' => $this->user_firstname,
        'ln' => $this->user_lastname,
        'un' => $this->getUsername(),
        'initials' => $this->getInitials(),
      ],
      'name' => $this->getUsername(),
      'recipes' => [
        'count' => (!is_null($this->user_statistics) ? $this->user_statistics['recipes_created'] : 0),
        'list' => $this->getRecipeListing(),
      ],
      'recipeSettings' => [
        'longDurationRecipes' => [
          'showWarning' => $Controller->Config()->Defaults('Recipes', 'LtWarning'),
          'longDurationMinutes' => $Controller->Config()->Defaults('Recipes', 'LtMinutes'),
        ],
      ],
      'statistics' => [
        'pictures' => [
          'uploaded' => (!is_null($this->user_statistics) ? $this->user_statistics['recipes_pictures_uploaded'] : 0),
        ],
        'recipes' => [
          'cooked' => (!is_null($this->user_statistics) ? $this->user_statistics['distinct_recipes_cooked'] : 0),
          'created' => (!is_null($this->user_statistics) ? $this->user_statistics['recipes_published'] : 0),
          'createdext' => (!is_null($this->user_statistics) ? $this->user_statistics['recipes_published_external'] : 0),
          'distinctviews' => (!is_null($this->user_statistics) ? $this->user_statistics['distinct_recipes_viewed'] : 0),
          'viewed' => (!is_null($this->user_statistics) ? $this->user_statistics['recipes_viewed'] : 0),
          'voted' => (!is_null($this->user_statistics) ? $this->user_statistics['distinct_recipes_voted_hearts'] : 0),
        ],
      ],
    ];
  }

  private function getJsonObj_Simple(): array
  {
    $this->loadStatistics();
    return [
      'id' => $this->user_id,
      'name' => $this->getUsername(),
      'meta' => [
        'email' => $this->exposeMail() ? $this->user_email : null,
        'fn' => $this->user_firstname,
        'ln' => $this->user_lastname,
        'un' => $this->getUsername(),
        'initials' => $this->getInitials(),
      ],
      'statistics' => [
        'recipes' => [
          'created' => (!is_null($this->user_statistics) ? $this->user_statistics['recipes_published'] : 0),
          'createdext' => (!is_null($this->user_statistics) ? $this->user_statistics['recipes_published_external'] : 0),
        ],
      ],
    ];
  }

  public function getLastname(): string
  {
    return $this->user_lastname;
  }

  public function getLastActivityTime(): ?DateTime
  {
    return $this->user_last_activity;
  }

  public function getMail(): string
  {
    return $this->user_email;
  }

  public function getName(): string
  {
    return $this->user_fullname;
  }

  /* public function getRecipeCount(): int
  {
    if ($this->recipe_count == -1) {
      global $Controller;
      $query = new QueryBuilder(EQueryType::qtSELECT, 'recipes');
      $query->select([['*', EAggregationType::atCOUNT, 'count']])
        ->where('recipes', 'user_id', '=', $this->user_id);
      if (!$Controller->isAuthenticated() || $Controller->User()->getId() != $this->getId())
        $query->andWhere('recipes', 'recipe_public', '=', 1);
      $this->recipe_count = $Controller->select($query)->fetch_assoc()['count'];
    }
    return $this->recipe_count;
  } */

  private function getRecipeListing(): array
  {
    global $Controller;
    $items = [];
    $stmt = $Controller->prepare('SELECT * FROM recipes_my WHERE user_id = ?');
    $stmt->bind_param('i', $this->user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($record = $result->fetch_assoc()) {
      $items[] = [
        'id' => $record['recipe_id'],
        'name' => $record['recipe_name'],
        'public' => ConverterHelper::to_bool($record['recipe_public_internal']) || ConverterHelper::to_bool($record['recipe_public_external']),
      ];
    }
    return $items;
  }

  public function getSession(): ?Session
  {
    return $this->session;
  }

  public function getUsername(): string
  {
    return (!is_null($this->oauth_user_name) ? $this->oauth_user_name : $this->user_name);
  }

  /* public function getValidationCode(): string
  {
    return $this->user_email_validation;
  }

  public function grantAdmin(): bool
  {
    global $Controller;
    if (
      ISWEB === false || (
        $Controller->isAuthenticated() &&
        $Controller->User()->isAdmin()
      )
    ) {
      $this->user_isadmin = true;
      $this->changes['user_isadmin'] = $this->user_isadmin;
      $Controller->updateDbObject($this);
      return true;
    }
    return false;
  }

  public function hasHash(): bool
  {
    return !is_null($this->user_hash);
  }

  public function hasRegistrationCompleted(): bool
  {
    return !is_null($this->user_registration_completed);
  } */

  public function isAdmin(): bool
  {
    return $this->user_isadmin;
  }

  public function isOAuthUser(): bool
  {
    return !is_null($this->oauth_user_name);
  }

  public function jsonSerialize(): array
  {
    return [
      'id' => $this->user_id,
      'name' => $this->getUsername(),
    ];
  }

  public static function load(int|string|null $id): ?self
  {
    if (is_null($id))
      return null;
    $stmt = is_int($id) ? self::loadById($id) : self::loadByName($id);
    if (is_null($stmt) || $stmt === false)
      return null;
    global $Controller;
    $stmt->bind_param('s', $id);
    if (!$stmt->execute()) {
      $Controller->loge(__CLASS__, __METHOD__, __LINE__, 'Load db user failed', [$id, $stmt->error, $stmt->errno]);
      return null;
    }
    $result = $stmt->get_result();
    if ($result->num_rows == 0) {
      $Controller->logi(__CLASS__, __METHOD__, __LINE__, 'User not found', [$id, $stmt->error, $stmt->errno]);
      return null;
    }
    return $result->fetch_object(self::class);
  }

  private static function loadById(int $id): mysqli_stmt|bool|null
  {
    global $Controller;
    return $Controller->prepare('SELECT * FROM users WHERE user_id = ? LIMIT 1');
  }

  private static function loadByName(string $name): mysqli_stmt|bool|null
  {
    global $Controller;
    return $Controller->prepare('SELECT * FROM users WHERE user_name = ? LIMIT 1');
  }

  private function loadStatistics(): void
  {
    if (!is_null($this->user_statistics))
      return;
    global $Controller;
    $stmt = $Controller->prepare('SELECT * FROM user_statistics WHERE user_id = ?');
    if (is_null($stmt) || $stmt === false)
      return;
    $stmt->bind_param('i', $this->user_id);
    if (!$stmt->execute())
      return;
    $this->user_statistics = $stmt->get_result()->fetch_assoc();
  }

  /* public function rejectAdmin(): bool
  {
    global $Controller;
    if (
      ISWEB === false || (
        $Controller->isAuthenticated() &&
        $Controller->User()->isAdmin()
      )
    ) {
      $this->user_isadmin = false;
      $this->changes['user_isadmin'] = $this->user_isadmin;
      $Controller->updateDbObject($this);
      return true;
    }
    return false;
  }

  public function setConsent_Sys2Me_Mail(bool $newValue): void
  {
    global $Controller;
    $this->consent_sys2user_mail = $newValue ? new \DateTime() : false;
    $this->changes['consent_sys2user_mail'] = $newValue ? $this->consent_sys2user_mail->format(DTF_SQL) : null;
    $Controller->updateDbObject($this);
  }

  public function setConsent_Sys2Me_Message(bool $newValue): void
  {
    global $Controller;
    $this->consent_sys2user_msg = $newValue ? new \DateTime() : false;
    $this->changes['consent_sys2user_msg'] = $newValue ? $this->consent_sys2user_msg->format(DTF_SQL) : null;
    $Controller->updateDbObject($this);
  }

  public function setConsent_User2Me_ExposeMail(bool $newValue): void
  {
    global $Controller;
    $this->consent_user2user_expose_mail = $newValue ? new \DateTime() : false;
    $this->changes['consent_user2user_expose_mail'] = $newValue ? $this->consent_user2user_expose_mail->format(DTF_SQL) : null;
    $Controller->updateDbObject($this);
  }

  public function setConsent_User2Me_Mail(bool $newValue): void
  {
    global $Controller;
    $this->consent_user2user_mail = $newValue ? new \DateTime() : false;
    if ($newValue == false)
      $this->setConsent_User2Me_ExposeMail(false);
    $this->changes['consent_user2user_mail'] = $newValue ? $this->consent_user2user_mail->format(DTF_SQL) : null;
    $Controller->updateDbObject($this);
  }

  public function setConsent_User2Me_Message(bool $newValue): void
  {
    global $Controller;
    $this->consent_user2user_msg = $newValue ? new \DateTime() : false;
    $this->changes['consent_user2user_msg'] = $newValue ? $this->consent_user2user_msg->format(DTF_SQL) : null;
    $Controller->updateDbObject($this);
  }

  public function setFirstname(string $newValue): void
  {
    global $Controller;
    $this->user_firstname = $newValue;
    $this->user_fullname = $this->user_firstname . ' ' . $this->user_lastname;
    $this->changes['user_firstname'] = $this->user_firstname;
    $this->changes['user_fullname'] = $this->user_fullname;
    $Controller->updateDbObject($this);
  }

  public function setLastname(string $newValue): void
  {
    global $Controller;
    $this->user_lastname = $newValue;
    $this->user_fullname = $this->user_firstname . ' ' . $this->user_lastname;
    $this->changes['user_lastname'] = $this->user_lastname;
    $this->changes['user_fullname'] = $this->user_fullname;
    $Controller->updateDbObject($this);
  }

  public function setMail(string $newValue): bool
  {
    global $Controller;
    if ($newValue != '') {
      $filter = filter_var($newValue, FILTER_VALIDATE_EMAIL);
      if ($filter == false)
        return false;
      $newuser = $Controller->OM()->User($newValue);
      if (!is_null($newuser))
        return false;
    }
    $this->user_email = $newValue;
    $this->changes['user_email'] = $this->user_email;
    $Controller->updateDbObject($this);
    return true;
  }

  public function setName(string $newValue): void
  {
    global $Controller;
    $this->user_fullname = $newValue;
    $this->changes['user_fullname'] = $this->user_fullname;
    $parts = explode(' ', $this->user_fullname);
    if (count($parts) == 1)
      $this->setFirstname($parts[0]);
    elseif (count($parts) == 2) {
      $this->setFirstname($parts[0]);
      $this->setLastname($parts[1]);
    } elseif (count($parts) == 3) {
      $this->setFirstname(implode(' ', [$parts[0], $parts[1]]));
      $this->setLastname($parts[2]);
    } elseif (count($parts) == 3) {
      $this->setFirstname(implode(' ', [$parts[0], $parts[1]]));
      $this->setLastname(implode(' ', [$parts[2], $parts[3]]));
    }
    $Controller->updateDbObject($this);
  }

  public function setPassword(string $newPassword, string $oldPassword): bool
  {
    global $Controller;
    if ($this->user_password == '********' || password_verify($oldPassword, $this->user_password)) {
      $this->user_password = password_hash($newPassword, PASSWORD_ARGON2I, ['threads' => $Controller->Config()->System('Checksums', 'PwHashThreads')]);
      $this->changes['user_password'] = $this->user_password;
      $Controller->updateDbObject($this);
      return true;
    }
    return false;
  }

  public function setRegistrationCompleted(): void
  {
    global $Controller;
    $this->user_registration_completed = new DateTime();
    $this->changes['user_registration_completed'] = $this->user_registration_completed->format(DTF_SQL);
    $Controller->updateDbObject($this);
  }

  public function validateEmail(string $token): bool
  {
    global $Controller;
    if ($this->user_email_validation == $token) {
      $this->user_email_validation = '';
      $this->user_email_validated = new DateTime();
      $this->changes['user_email_validation'] = '';
      $this->changes['user_email_validated'] = $this->user_email_validated->format(DTF_SQL);
      $Controller->updateDbObject($this);
      return true;
    }
    return false;
  }

  public function verify(string $password): bool
  {
    global $Controller;
    if (password_verify($password, $this->user_password)) {
      if (password_needs_rehash($this->user_password, PASSWORD_ARGON2I, ['threads' => $Controller->Config()->System('Checksums', 'PwHashThreads')])) {
        $this->user_password = password_hash($password, PASSWORD_ARGON2I, ['threads' => $Controller->Config()->System('Checksums', 'PwHashThreads')]);
        $this->changes['user_password'] = $this->user_password;
        $Controller->updateDbObject($this);
      }
      return true;
    }
    return false;
  }
 */
  public function verifySession(string $session_token, string $session_password): bool
  {
    global $Controller;
    $query = new QueryBuilder(EQueryType::qtSELECT, 'user_logins', DB_ANY);
    $query->where('user_logins', 'user_id', '=', $this->user_id)
      ->orderBy([['login_time', 'DESC']]);
    $result = $Controller->select($query);
    if (!$result || $result->num_rows == 0)
      return false;
    while ($record = $result->fetch_assoc()) {
      if (password_verify($session_token, $record['login_token'])) {
        $pwdhash = HashHelper::hash(substr($session_token, 0, 16));
        $pwdhash .= $session_password;
        $pwdhash .= HashHelper::hash(substr($session_token, 16));

        if (password_verify($pwdhash, $record['login_password'])) {
          $uptime = new DateTime();

          $query = new QueryBuilder(EQueryType::qtUPDATE, 'user_logins');
          $query->update(['login_time' => $uptime->format('Y-m-d H:i:s')]);
          $query->where('user_logins', 'login_id', '=', intval($record['login_id']));
          $Controller->update($query);

          $record['login_time'] = $uptime->format('Y-m-d H:i:s');
          $this->session = new Session($this, $record);
          return true;
        }
      }
    }
    return false;
  }

}