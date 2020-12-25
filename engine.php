<?php

// These inputs could be dynamic as many as you have PDBs for later design of course with update the structure down.
$pdb1 = $_FILES['pdb1'];
$pdb2 = $_FILES['pdb2'];
$fasta = $_FILES['fasta'];

// Debugging purpose only
function dd($raw)
{
    echo "<pre>";
    if (is_array($raw)) {
        print_r($raw);
    } else {
        echo $raw;
    }
    echo "</pre>";
    die;
}

// Helper class to fill atoms of PDB
class PDB
{
    public $atoms = [];
    public $text = [];

    public function PDB($path)
    {
        $file = fopen($path, "r");
        while (!feof($file)) {
            $line = fgets($file);
            $lineArray = $this->separate($line);
            if (isset($lineArray[0]) && isset($lineArray[0][0]) && $lineArray[0][0] == 'ATOM') {
                $this->atoms[$lineArray[0][1]] = $lineArray;
                $this->text[$lineArray[0][1]] = $line;
            }
        }
        fclose($file);
    }

    private function separate($line)
    {
        $re = '/[\w\d\.\-\>\#\,]+/';
        preg_match_all($re, $line, $matches, PREG_UNMATCHED_AS_NULL, 0);
        return is_array($matches) ? $matches : [];
    }
}

// Helper class to fill resedius of Protine 
class Dssp
{
    public $resedius = [];

    public function Dssp($array)
    {
        $this->readResedius($array);
    }

    private function readResedius($array)
    {
        array_splice($array, 0, 27);
        $headers = $array[0];
        array_shift($array);

        foreach ($array as $line) {
            $resediu = $this->separate($line, true)[0];
            $this->resedius[$resediu[0]] = [
                'num' => $resediu[0],
                'seq' => $resediu[1],
                'ss' => $resediu[4],
            ];
        }
    }

    private function separate($line, $w = false)
    {
        $re = '/[\w\d\.\-\>\#\,\+]+/';
        preg_match_all($re, $w ? $this->removeWhiteSpace($line) : $line, $matches, PREG_UNMATCHED_AS_NULL, 0);
        return is_array($matches) ? $matches : [];
    }

    private function removeWhiteSpace($line)
    {
        $re = '/(\d+\,\s[\d\.]+)/';
        preg_match_all($re, $line, $matches, PREG_UNMATCHED_AS_NULL, 0);
        foreach ($matches as $match) {
            if (empty($match)) continue;

            $item = $match[0];
            $item = preg_replace('/\s/', '', $item);
            $line = str_replace($match[0], $item, $line);
        }
        return $line;
    }
}

// Read fasta file content and get second line
function readFasta($path)
{
    $fasta = "";
    $file = fopen($path, "r");
    while (!feof($file)) {
        $line = fgets($file);
        if (!preg_match('/\>/', $line)) {
            $fasta = $line;
            break;
        }
    }
    fclose($file);
    return $fasta;
}

// Write align.txt file with fasta content
function updateAlign($fasta)
{
    $content = "> model1 \n";
    $content .= $fasta . "\n";
    $content .= "> model2 \n";
    $content .= $fasta . "\n";

    file_put_contents(dirname(__FILE__) . '/align.txt', $content);
    return $content;
}

// Read PDB files ATOMs
$p1 = new PDB($pdb1['tmp_name']);
$p2 = new PDB($pdb2['tmp_name']);

// Invoke align.txt file write function
$fastaStruct = updateAlign(readFasta($fasta['tmp_name']));

// Get DSSP result for both PDBs
exec(dirname(__FILE__) . '/mkdssp ' . $pdb1['tmp_name'], $pdb1Output);
exec(dirname(__FILE__) . '/mkdssp ' . $pdb2['tmp_name'], $pdb2Output);

// Compare the two DSSP files and generate new PDBS
$dssp1 = new Dssp($pdb1Output);
$dssp2 = new Dssp($pdb2Output);
$keep = [];
$commonNum = [];
// Set the longest and shortest array to loop to count all occurrencies
$baseArray = count($dssp1->resedius) > count($dssp2->resedius) ? $dssp1->resedius : $dssp2->resedius;
$subArray = count($dssp1->resedius) > count($dssp2->resedius) ? $dssp2->resedius : $dssp1->resedius;
foreach ($baseArray as $num => $resediu) {
    // Filter resedius array to fill keep array with common resediu sequence number and secondary structure. 
    array_filter($subArray, function ($r) use ($resediu, &$keep, &$commonNum) {
        $condition = $r['seq'] == $resediu['seq'] && $r['ss'] == $resediu['ss'];
        if ($condition && !in_array($r['seq'], $keep)) {
            $keep[] = $r['seq'];
            $commonNum[] = $r['num'];
        }
        return $condition;
    });
}
// Sort sequence array
sort($keep);
// Filter atoms of first PDB whcih exist in common array "keep"
$pdb1New = array_filter($p1->atoms, function ($atom) use ($keep) {
    return in_array($atom[0][5], $keep);
});
// Filter atoms of second PDB whcih exist in common array "keep"
$pdb2New = array_filter($p2->atoms, function ($atom) use ($keep) {
    return  in_array($atom[0][5], $keep);
});
$pdb1NewContent = "";
$pdb2NewContent = "";
$customFileLine1 = "";
$customFileLine2 = "";
$customFileLine3 = "";
$customFileLine4 = "";
$customFileLine5 = "";
// Build the content of the first new PDB file
foreach ($pdb1New as $atom) {
    $text = $p1->text[$atom[0][1]];
    $pdb1NewContent .= $text . "\n"; // The new line char is important

    // $resediu = $dssp1->resedius[$atom[0][1]];
}
// Build the content of the second new PDB file
foreach ($pdb2New as $atom) {
    $text = $p2->text[$atom[0][1]];
    $pdb2NewContent .= $text . "\n";
}
// Write data to filesystem 
$pdb1NewFileName = str_replace('.pdb', '', $pdb1['name']) . '_new.pdb';
$pdb2NewFileName = str_replace('.pdb', '', $pdb2['name']) . '_new.pdb';
file_put_contents($pdb1NewFileName, $pdb1NewContent);
file_put_contents($pdb2NewFileName, $pdb2NewContent);

foreach($dssp1->resedius as $resediu) {
    $customFileLine1 .= $resediu['num'] . " ";
    $customFileLine2 .= $resediu['ss'] . " ";
    
    $customFileLine3 .= in_array($resediu['num'], $commonNum) ? ": " : "  ";
}
foreach($dssp2->resedius as $resediu) {
    $customFileLine4 .= $resediu['num'] . " ";
    $customFileLine5 .= $resediu['ss'] . " ";
}
$customFileLine1 .= "\n" . $customFileLine2 . "\n" . $customFileLine3 . "\n" . $customFileLine4 . "\n" . $customFileLine5 . "\n";
file_put_contents('custom.txt', $customFileLine1);

// Run TMalign with amended PDBs from DSSP
exec(dirname(__FILE__) . '/TMalign ' . (dirname(__FILE__) . "/{$pdb1NewFileName}") . ' ' . (dirname(__FILE__) . "/{$pdb2NewFileName}"), $tmOutput);
$tmContent = "";
foreach($tmOutput as $line) {
    $tmContent .= $line . "\n";
}
// Write TMalign result to filesystem
file_put_contents('tmalign_result.txt', $tmContent);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customized DSSP & TMalign</title>

    <style>
        label { 
            display: block;
            color: red;
        }
    </style>
</head>
<body>
    <a href="index.php">Back</a>
    <h2>Customized DSSP & TMalign by Fatima Shhimi</h2>
    <table border="1" style="width: 400px;">
    <tbody>
    <tr>
    <th align="left">custom.txt</th>
    <td><a href="custom.txt" target="_blank">Click To Download</a></td>
    </tr>
    <tr>
    <th align="left">align.txt</th>
    <td><a href="align.txt" target="_blank">Click To Download</a></td>
    </tr>
    <tr>
    <th align="left"><?= $pdb1NewFileName ?></th>
    <td><a href="<?= $pdb1NewFileName ?>" target="_blank">Click To Download</a></td>
    </tr>
    <tr>
    <th align="left"><?= $pdb2NewFileName ?></th>
    <td><a href="<?= $pdb2NewFileName ?>" target="_blank">Click To Download</a></td>
    </tr>
    <tr>
    <th align="left">tmalign_result.txt</th>
    <td><a href="tmalign_result.txt" target="_blank">Click To Download</a></td>
    </tr>
    </tbody>
    </table>
    <hr>
    <div>
        <label>Custom Structure</label>
        <pre><?= $customFileLine1 ?></pre>
    </div>
    <div>
        <label>FASTA Content</label>
        <pre><?= $fastaStruct ?></pre>
    </div>
    <hr>
    <div>
        <label>TMalign Result Content</label>
        <pre><?= $tmContent ?></pre>
    </div>
</body>
</html>