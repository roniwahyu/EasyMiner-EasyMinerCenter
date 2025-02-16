<?php

namespace EasyMinerCenter\Model\Data\Entities;

/**
 * Class DbColumn
 * @package EasyMinerCenter\Model\Data\Entities
 */
class DbColumn {
  /** @var string $name */
  public $name = '';
  /** @var string $dataType */
  public $dataType = '';
  /** @var string $strLength */
  public $strLength = 0;

  const TYPE_STRING='string';
  const TYPE_INTEGER='int';
  const TYPE_FLOAT='float';
} 