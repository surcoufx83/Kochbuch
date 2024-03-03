<?php

namespace Surcouf\Cookbook\User\Session;

use \DateTime;
use Surcouf\Cookbook\User\User;
use Surcouf\Cookbook\Database\EQueryType;
use Surcouf\Cookbook\Database\QueryBuilder;
use Surcouf\Cookbook\Helper\ConverterHelper;
use League\OAuth2\Client\Token\AccessToken;

if (!defined('CORE2'))
  exit;

class Session
{

  private string|int $login_id, $user_id;
  private string|bool $login_keep;
  private string|DateTime $login_time;
  private string $login_token, $login_password, $login_oauthdata;
  private AccessToken|null $oauthToken;

  public function __construct(User $user, $data)
  {
    $this->login_id = intval($data['login_id']);
    $this->user_id = intval($data['user_id']);
    $this->login_time = new DateTime($data['login_time']);
    $this->login_keep = ConverterHelper::to_bool($data['login_keep']);
    $this->oauthToken = (!is_null($data['login_oauthdata']) ? new AccessToken(json_decode($data['login_oauthdata'], true)) : null);
    if (!is_null($this->oauthToken))
      $this->renewToken();
  }

  public function destroy(): void
  {
    global $Controller;
    $query = new QueryBuilder(EQueryType::qtDELETE, 'user_logins');
    $query->where('user_logins', 'login_id', '=', $this->login_id)
      ->limit(1);
    $Controller->delete($query);
  }
  public function getId(): int
  {
    return $this->login_id;
  }

  public function getToken(): ?AccessToken
  {
    return $this->oauthToken;
  }

  public function getUserId(): int
  {
    return $this->user_id;
  }

  public function isExpired(): bool
  {
    return ($this->isOAuthSession() && $this->oauthToken->hasExpired());
  }

  public function isOAuthSession(): bool
  {
    return !is_null($this->oauthToken);
  }

  public function keep(): bool
  {
    return $this->login_keep;
  }

  private function renewToken(): void
  {
    global $Controller;
    /* if ($this->oauthToken->hasExpired()) {
      $provider = $Controller->getOAuthProvider();
      try {
        $newToken = $provider->getAccessToken('refresh_token', [
          'refresh_token' => $this->oauthToken->getRefreshToken()
        ]);
        $this->oauthToken = $newToken;
        $query = 'UPDATE user_logins SET login_oauthdata = ? WHERE login_id = ?';
        $stmt = $Controller->prepare($query);
        $login_oauthdata = json_encode($newToken->jsonSerialize());
        $stmt->bind_param('si', $login_oauthdata, $this->id);
        if (!$stmt->execute())
          $Controller->loge(__CLASS__, __METHOD__, __LINE__, 'Failed executing Stmt: ' . $stmt->error, [$query, $this->id, $login_oauthdata, $stmt->errno, $stmt->error]);

        /* $this->changes['login_oauthdata'] = json_encode($newToken->jsonSerialize());
        $Controller->updateDbObject($this); *
      } catch (\Exception $e) {
        $Controller->logf(__CLASS__, __METHOD__, __LINE__, $e, 'Failed refreshing token.');
        /* todo: fix missing refresh */
    /*
    var_dump('Exception refreshing OAuth token!', $this->oauthToken->getRefreshToken(), $e->getMessage(), $e);
    exit;
    $Controller->Dispatcher()->forwardTo($Controller->getLink('private:login-oauth2'));
    *
  }
} */
  }

}