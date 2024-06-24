<?php

namespace Surcouf\Cookbook\Response;

use DateTime;
use JsonSerializable;
use Surcouf\Cookbook\Recipe\Recipe;

if (!defined('CORE2'))
  exit;

class AiResponse implements JsonSerializable
{

  public string|bool $success = false;
  public string|null $errorMessage = null;
  private string|null $ai_id = null;
  private string|null $ai_object = null;
  private string|int|null $ai_created = null;
  private string|null $ai_model = null;
  public AiTokenUsage|null $usage = null;
  private string|null $msg_role = null;
  public $jsonPayload = null;

  public function __construct(string|bool $json)
  {
    global $Controller;
    $this->success = is_string($json);
    if ($this->success) {
      $json = json_decode($json, true);
      $Controller->logi(__CLASS__, __METHOD__, __LINE__, 'AI Response', [$json]);
      if (array_key_exists('error', $json)) {
        $this->success = false;
        $this->errorMessage = $json['error']['message'];
      } else {
        $this->ai_id = array_key_exists('id', $json) ? $json['id'] : null;
        $this->ai_object = array_key_exists('object', $json) ? $json['object'] : null;
        $this->ai_created = array_key_exists('created', $json) ? intval($json['created']) : null;
        $this->ai_model = array_key_exists('model', $json) ? $json['model'] : null;
        $this->usage = array_key_exists('usage', $json) ? new AiTokenUsage($json['usage']) : null;
        if (array_key_exists('choices', $json) && is_array($json['choices']) && count($json['choices']) == 1) {
          $msg = $json['choices'][0];
          if (array_key_exists('message', $msg)) {
            $this->msg_role = array_key_exists('role', $msg['message']) ? $msg['message']['role'] : null;
            if (array_key_exists('content', $msg['message']))
              $this->__construct_extractJson($msg['message']['content']);
          }
        }
      }
    }
  }

  private function __construct_extractJson(string $content)
  {
    global $Controller;

    $i = strpos($content, '```json');
    $content2 = false;
    if ($i !== false && $i >= 0) {
      $i += 7;
      $e = strpos($content, '```', $i);
      if ($e !== false && $e > 0) {
        $content2 = substr($content, $i, $e - $i);
      }
    }

    $Controller->logi(__CLASS__, __METHOD__, __LINE__, 'AI Response message content', [$content2]);
    if (!$content2)
      return;

    $content2 = json_decode(str_replace("\r\n", '\r\n', $content2), true);

    if (is_null($content2) || !is_array($content2)) {
      $this->errorMessage = $content;
      $this->success = false;
      return;
    }
    if (array_key_exists('error', $content2)) {
      $this->errorMessage = $content2['error'];
      $this->success = false;
    } else
      $this->jsonPayload = $content2;
  }

  public function jsonSerialize(): mixed
  {
    return [
      'success' => $this->success,
      'error' => $this->errorMessage,
      'ai' => [
        'id' => $this->ai_id,
        'object' => $this->ai_object,
        'created' => $this->ai_created,
        'model' => $this->ai_model,
        'tokenUsage' => $this->usage,
        'role' => $this->msg_role,
      ],
      'jsonPayload' => $this->jsonPayload,
    ];
  }

}

class AiTokenUsage implements JsonSerializable
{
  public string|int $prompt_tokens;
  public string|int $completion_tokens;
  public string|int $total_tokens;

  public function __construct(array $ar)
  {
    $this->prompt_tokens = array_key_exists('prompt_tokens', $ar) ? intval($ar['prompt_tokens']) : null;
    $this->completion_tokens = array_key_exists('completion_tokens', $ar) ? intval($ar['completion_tokens']) : null;
    $this->total_tokens = array_key_exists('total_tokens', $ar) ? intval($ar['total_tokens']) : null;
  }

  public function jsonSerialize(): mixed
  {
    return [
      'prompt' => $this->prompt_tokens,
      'completion' => $this->completion_tokens,
      'total' => $this->total_tokens,
    ];
  }

}