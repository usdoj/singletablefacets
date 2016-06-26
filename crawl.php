<?php

require_once __DIR__ . '/vendor/autoload.php';

use \USDOJ\SingleTableFacets\KeywordCrawler;

$docs = array(
  'opa/pr/three-mississippi-correctional-officers-indicted-inmate-assault-and-cover',
  'http://ojp.gov/funding/Apply/Resources/FinancialCapability.pdf',
  'https://www.fara.gov/forms/2011/OMB_1124_0003.pdf',
);

foreach ($docs as $doc) {
  $crawler = new KeywordCrawler($doc, 'https://www.justice.gov/');
  print_r($crawler->getKeywords());
}