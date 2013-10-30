<?php
/**
 * Allows creation of new URY members!
 * 
 * @author Lloyd Wallis <lpw@ury.org.uk>
 * @version 20130717
 * @package MyRadio_Profile
 */
$data = User::getBulkAddForm()->readValues();
$template = CoreUtils::getTemplateObject();

for ($i = 0; $i < sizeof($data['bulkaddrepeater']['fname']); $i++) {
  $params = array();
  foreach ($data['bulkaddrepeater'] as $key => $v) {
    $params[$key] = $data['bulkaddrepeater'][$key][$i];
  }
  try {
    $user = User::create($params['fname'], $params['sname'], $params['eduroam'],
            $params['sex'], $params['collegeid']);
    $template->addInfo('Added Member with ID '.$user->getID());
  } catch (MyRadioException $e) {
    $template->addError('Could not add '.$params['eduroam'].': '.$e->getMessage());
  }
}

$template->setTemplate('MyRadio/text.twig')->render();