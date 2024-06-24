<?php

namespace Surcouf\Cookbook\User;

use Surcouf\Cookbook\Mail;
use Surcouf\Cookbook\Helper\HashHelper;
use \DateTime;

if (!defined('CORE2'))
  exit;

class BlankUser extends User
{

  public function __construct(string $firstname, string $lastname, string $username, string $email)
  {
    $this->user_name = $username;
    $this->user_firstname = $firstname;
    $this->user_lastname = $lastname;
    $this->user_fullname = $firstname . ' ' . $lastname;
    $this->user_password = '********';
    $this->user_email = $email;
    $this->user_email_validation = HashHelper::generate_token(12);
    $this->user_registration_completed = new DateTime();
  }

  public function sendActivationMail(array &$response): bool
  {
    return false;
  }

  public function save(array &$response): bool
  {
    return false;
  }

}
