<?php

class AdditionalInformationService {
  private $wsdlUrl;
  private $username;
  private $group;
  private $password;
  private $current_service;

  public function __construct($wsdlUrl, $username, $group, $password) {
    $this->wsdlUrl = $wsdlUrl;
    $this->username = $username;
    $this->group = $group;
    $this->password = $password;
  }

  public function getByIsbn($isbn) {
    $isbn = str_replace('-', '', $isbn);

    $identifiers = $this->collectIdentifiers('isbn', $isbn);
    $response = $this->sendRequest($identifiers);
    return $this->extractAdditionalInformation('isbn', $response);
  }

  public function getByFaustNumber($faustNumber) {
    $identifiers = $this->collectIdentifiers('faust', $faustNumber);
    $response = $this->sendRequest($identifiers);
    return $this->extractAdditionalInformation('faust', $response);
  }

  protected function collectIdentifiers($idName, $ids) {
    if (!is_array($ids)) {
      $ids = array($ids);
    }

    $identifiers = array();
    foreach ($ids as $i) {
      $identifiers[] = array($idName => $i);
    }

    return $identifiers;
  }

  protected function sendRequest($identifiers) {
    $ids = array();
    foreach ($identifiers as $i) {
      $ids = array_merge($ids, array_values($i));
    }

    $authInfo = array('authenticationUser' => $this->username,
                      'authenticationGroup' => $this->group,
                      'authenticationPassword' => $this->password);
    $client = new SoapClient($this->wsdlUrl);

    $startTime = explode(' ', microtime());
    $response = NULL;

    $cover_service = variable_get('current_cover_service');
    $method = '';

    if ($cover_service == SERVICE_ADDI) {
      $method = 'additionalInformation';
      $this->current_service = SERVICE_ADDI;
    }
    elseif ($cover_service == SERVICE_MOREINFO) {
      $method = 'moreInfo';
      $this->current_service = SERVICE_MOREINFO;
    }
    else {
      throw new AdditionalInformationServiceException('Current cover service not set, check the settings.');
    }

    try {
      $response = $client->$method(array(
                          'authentication' => $authInfo,
                          'identifier' => $identifiers));
    }
    catch (Exception $e) {
      // Re-throw Addi specific exception.
      throw new AdditionalInformationServiceException($e->getMessage());
    }

    $stopTime = explode(' ', microtime());
    $time = floatval(($stopTime[1] + $stopTime[0]) - ($startTime[1] + $startTime[0]));

    //Drupal specific code - consider moving this elsewhere
    if (variable_get('addi_enable_logging', FALSE)) {
      watchdog('addi', 'Completed request (' . round($time, 3) . 's): Ids: %ids', array('%ids' => implode(', ', $ids)), WATCHDOG_DEBUG, 'http://' . $_SERVER["HTTP_HOST"] . $_SERVER["REQUEST_URI"]);
    }

    if ($response->requestStatus->statusEnum != 'ok') {
      throw new AdditionalInformationServiceException($response->requestStatus->statusEnum . ': ' . $response->requestStatus->errorText);
    }

    if (!is_array($response->identifierInformation)) {
      $response->identifierInformation = array($response->identifierInformation);
    }

    return $response;
  }

  protected function extractAdditionalInformation($idName, $response) {
    $additionalInformations = array();

    foreach ($response->identifierInformation as $info) {
      $thumbnailUrl = $detailUrl = NULL;
      $cover_image = ($this->current_service == SERVICE_MOREINFO && isset($info->coverImage))
                        ? $info->coverImage : (isset($info->image) ? $info->image : NULL);

      if (isset($info->identifierKnown) && $info->identifierKnown && $cover_image) {
        if (!is_array($cover_image)) {
          $cover_image = array($cover_image);
        }

        foreach ($cover_image as $image) {
          switch ($image->imageSize) {
            case 'thumbnail':
              $thumbnailUrl = $image->_;
              break;
            case 'detail':
              $detailUrl = $image->_;
              break;
            default:
              // Do nothing other image sizes may appear but ignore them for now
          }
        }

        $additionalInfo = new AdditionalInformation($thumbnailUrl, $detailUrl);
        $additionalInformations[$info->identifier->$idName] = $additionalInfo;
      }
    }

    return $additionalInformations;
  }
}
