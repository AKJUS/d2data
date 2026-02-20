<?php

declare(strict_types=1);

/**
 * @file
 * Walk and flatten a TC tree, including unique/set/rare/magic factors.
 */

chdir(realpath(__DIR__));

define('TREASURECLASSEX', json_decode(file_get_contents('json/treasureclassex.json'), TRUE));
define('TREASURECLASSEXBASE', json_decode(file_get_contents('json/base/treasureclassex.json'), TRUE));
define('ATOMIC', json_decode(file_get_contents('json/atomic.json'), TRUE));
define('ATOMICBASE', json_decode(file_get_contents('json/base/atomic.json'), TRUE));

abstract class Task {
  public string $entry;
  public string $type;
  public float $probability;
}

abstract class NestedTask extends Task {
  public string $type;

  public function __construct(
    public string $entry,
    public float $probability,
    public array $data = [],
  ) {}
}

class Series extends NestedTask {
  public string $type = 'series';
}

class Parallel extends NestedTask {
  public string $type = 'parallel';
}

class Drop extends Task {
  public string $type = 'drop';

  public function __construct(
    public string $entry,
    public float $probability,
    public int $unique,
    public int $set,
    public int $rare,
    public int $magic,
  ) {}

}

class TreasureClassCalculation {

  public function __construct(
    public readonly int $dropmodifier,
    public readonly array $treasureclassex,
    public readonly array $atomic,
  ) {}

  public static function calculate(string $tc_name, int $dropmodifier = 1, array $treasureclassex = TREASURECLASSEX, array $atomic = ATOMIC): array {
    return (new self($dropmodifier, $treasureclassex, $atomic))->exec($tc_name);
  }

  protected static function noDrop(int $e, int $nd, int $d) {
    if ($e <= 1) {
      return $nd | 0;
    }

    if ($nd <= 0) {
      return 0;
    }

    if ($d < 1) {
        return INF;
    }

    return floor($d / (((($nd + $d) / $nd)**$e) - 1));
  }


  protected function walkTc(string $tc_name, float $probability, int $unique = 0, int $set = 0, int $rare = 0, int $magic = 0): ?NestedTask {
    if (!isset($this->treasureclassex[$tc_name])) {
      return NULL;
    }

    $tc = $this->treasureclassex[$tc_name];

    if (!isset($tc['Picks']) && $tc['Picks']) {
      return NULL;
    }

    $unique = max($unique, $tc["Unique"] ?? 0);
    $set = max($set, $tc["Set"] ?? 0);
    $rare = max($rare, $tc["Rare"] ?? 0);
    $magic = max($magic, $tc["Magic"] ?? 0);

    $items = [];

    for ($i = 0; $i <= 10; $i++) {
      if (isset($tc["Item$i"]) && isset($tc["Prob$i"])) {
        $items[$tc["Item$i"]] = $tc["Prob$i"];
      }
    }

    $picks = abs($tc['Picks']);
    $parallel = $tc['Picks'] > 0;
    $task = new Series($tc_name, $probability);

    if ($parallel) {
      $drop = array_sum($items);
      $nodrop = $this->noDrop($this->dropmodifier, $tc["NoDrop"] ?? 0, $drop);

      for ($i = 1; $i <= $picks; $i++) {
        $task->data[] = $subtask = new Parallel("$tc_name: Pick $i", 1);

        foreach ($items as $item => $prob) {
          $prob = $prob / ($drop + $nodrop);

          if (isset($this->treasureclassex[$item])) {
            $subtask->data[] = $this->walkTc($item, $prob, $unique, $set, $rare, $magic);
          }
          else {
            $subtask->data[] = new Drop($item, $prob, $unique, $set, $rare, $magic);
          }
        }
      }
    }
    else {
      foreach ($items as $item => $prob) {
        while ($prob > 0) {
          if (isset($this->treasureclassex[$item])) {
            $task->data[] = $this->walkTc($item, $prob, $unique, $set, $rare, $magic);
          }
          else {
            $task->data[] = new Drop($item, $prob, $unique, $set, $rare, $magic);
          }

          $prob--;
        }
      }
    }

    return $task;
  }

  protected function resolveTree(?Task $tree, float $probability, float &$probabilityleft = 6.0, array &$ret = [], array $stack = []) : array {
    if (!$tree) {
      return $ret;
    }

    $stack[] = $tree->entry;

    if ($probability <= 0 || $probabilityleft <= 0) {
      return $ret;
    }

    if ($tree instanceof Drop) {
      $prob = min($probabilityleft, $tree->probability * $probability);

      if ($prob > 0) {
        $items = $this->atomic[$tree->entry] ?? [
          $tree->entry => 1.0,
        ];

        foreach ($items as $code => $itemprob) {
          $itemprob *= $prob;
          $probabilityleft -= $itemprob;
          $key = implode('|', [$code, $tree->unique, $tree->set, $tree->rare, $tree->magic]);
          $ret[$key] ??= 0;
          $ret[$key] += $itemprob;
        }
      }
    }
    elseif ($tree instanceof Series) {
      foreach ($tree->data as $child) {
        $this->resolveTree($child, $tree->probability * $probability, $probabilityleft, $ret, $stack);
      }
    }
    elseif ($tree instanceof Parallel) {
      $pret = [];
      
      foreach ($tree->data as $child) {
        $pprob = $probabilityleft;
        $this->resolveTree($child, $tree->probability * $probability, $pprob, $pret, $stack);
      }

      $tprob = array_sum($pret);

      if ($tprob > $probabilityleft) {
        $scale = $probabilityleft / $tprob;

        foreach ($pret as $entry => $prob) {
          $prob *= $scale;
          $ret[$entry] ??= 0;
          $ret[$entry] += $prob;
          $probabilityleft -= $prob;
        }
      }
      else {
        foreach ($pret as $entry => $prob) {
          $ret[$entry] ??= 0;
          $ret[$entry] += $prob;
          $probabilityleft -= $prob;
        }
      }
    }

    return $ret;
  }

  protected function exec(string $tc_name): array {
    $tree = $this->walkTc($tc_name, 1.0);
    $dummy = 6;
    $final = $this->resolveTree($tree, 1.0, $dummy);
    asort($final, SORT_DESC);
    $final = array_reverse($final, TRUE);
    return $final;
  }

}

foreach ([
  'json/precalctc/' => [TREASURECLASSEX, ATOMIC],
  'json/base/precalctc/' => [TREASURECLASSEXBASE, ATOMICBASE],
] as $basepath => [$treasureclassex, $atomic]) {
  print("Generating $basepath\n");
  $files = glob($basepath . '*.json');

  foreach ($files as $file) {
    if (is_file($file) && $file[0] !== '.') {
      unlink($file);
    }
  }

  $index = [];
  $precalc = [];

  foreach ($treasureclassex as $tc_name => $tc) {
    foreach ([1, 2, 3, 4, 5, 6, 7, 8] as $dropmodifier) {
      $results = TreasureClassCalculation::calculate($tc_name, $dropmodifier, $treasureclassex, $atomic);

      if ($results) {
        $total = array_sum($results);

        if ($total > 6.000000001) {
          print("Warning: $tc_name @ $dropmodifier = $total\n");
        }

        $remapped = [];

        foreach ($results as $entry => $prob) {
          [$item, $unique, $set, $rare, $magic] = explode('|', $entry);

          $remapped[] = [
            $item,
            $prob,
            (int) $magic,
            (int) $rare,
            (int) $set,
            (int) $unique,
          ];
        }

        $precalc[$tc_name][0] ??= [];
        $precalc[$tc_name][1] ??= [];
        $precalc[$tc_name][2] ??= [];
        $precalc[$tc_name][3] ??= [];
        $precalc[$tc_name][4] ??= [];
        $precalc[$tc_name][5] ??= [];
        $precalc[$tc_name][6] ??= [];
        $precalc[$tc_name][7] ??= [];

        $precalc[$tc_name][$dropmodifier - 1] = $remapped;
      }
    }

    if ($precalc[$tc_name] ?? NULL) {
      $filename = preg_replace('/[^a-z0-9() -_]\+/i', '_', $tc_name);
      $filename = trim($filename, '_- ');
      $index[$tc_name] = $filename . ".json";
      $filepath = $basepath . $filename . ".json";

      if (file_exists($filepath)) {
        throw new Exception("File $filepath already exists");
      }

      file_put_contents($filepath, json_encode($precalc[$tc_name], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }
  }

  if ($index) {
    file_put_contents($basepath . '_index.json', json_encode($index, JSON_PRETTY_PRINT));

    $module = '';
    $module_map = [];

    $i = 1;

    foreach ($index as $tc_name => $filename) {
      $variable = $module_map[$tc_name] = "json$i";
      $module .= "import $variable from './$filename' with { type: 'json' };\n";
      $i++;
    }

    $module .= "\nexport default {\n";

    foreach ($module_map as $tc_name => $variable) {
      $module .= "  '$tc_name': $variable,\n";
    }

    $module .= "}\n";

    file_put_contents($basepath . '_all.mjs', $module);
  }
}
