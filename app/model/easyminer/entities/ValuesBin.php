<?php

namespace EasyMinerCenter\Model\EasyMiner\Entities;
use LeanMapper\Entity;

/**
 * Class ValuesBin
 * @package EasyMinerCenter\Model\EasyMiner\Entities
 * @property int $valuesBinId
 * @property string $name
 * @property Format $format m:hasOne
 * @property Interval[] $intervals m:hasMany
 * @property Value[] $values m:hasMany
 * @method addToValues
 * @method removeFromValues
 * @method addToIntervals
 * @method removeFromIntervals
 */
class ValuesBin extends Entity{

} 