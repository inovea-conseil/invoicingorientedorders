<?php

/* Superprod
 * Copyright (C) 2018       Inovea-conseil.com     <info@inovea-conseil.com>
 */
/**
 * \ingroup Superprod
 *
 * Superprod
 */
// Load Dolibarr environment
if (false === (@include '../../main.inc.php')) {  // From htdocs directory
	require '../../../main.inc.php'; // From "custom" directory
}
global $langs, $user;
// Libraries
require_once DOL_DOCUMENT_ROOT . "/core/lib/admin.lib.php";
require_once __DIR__ . "/../include/superprod.func.php";

require_once DOL_DOCUMENT_ROOT . "/societe/class/societe.class.php";
require_once DOL_DOCUMENT_ROOT . "/commande/class/commande.class.php";
require_once DOL_DOCUMENT_ROOT . "/product/class/product.class.php";

if (! empty($conf->productcase->enabled)) {
    dol_include_once('/productcase/class/productcase.class.php');
}
// Translations
$langs->load("superprod@superprod");
$langs->load("admin");
// Access control
if (!$user->rights->Superprod->RightSuperprodO11) {
    accessforbidden();
}

//on récupère la valeur auto refresh
$autorefresh = intval($conf->global->SUPERPROD_AUTO_REFRESH);

// on charge les affaire à produire ou en cours de production
$superprods = listAffaire($db, [0,1]);
?>

<!doctype html>
<html>
  <head>
    <!-- Required meta tags -->
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">

    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0/css/bootstrap.min.css" integrity="sha384-Gn5384xqQ1aoWXA+058RXPxPg6fy4IWvTNh0E263XmFcJlSAwiGgFAW/dAiS6JXm" crossorigin="anonymous">
    <link href="https://use.fontawesome.com/releases/v5.0.6/css/all.css" rel="stylesheet">
    <style>
        .affairelink:hover {
            text-decoration: none;
        }
    </style>
    <title><?= $langs->trans("SUPERPROD_SUPERVISION") ?></title>
    <?php if ($autorefresh > 0) : ?>
    <meta http-equiv="refresh" content="<?= $autorefresh ?>">
    <?php endif ?>
  </head>
  <body>
      <?php if ($user->rights->Superprod->RightSuperprodO21) : ?>
      <a class="close" style="font-size: 40px; padding: 20px;" aria-label="Close" href="<?= DOL_MAIN_URL_ROOT ?>">
        <span aria-hidden="true">&times;</span>
      </a>
      <?php endif ?>
      <br/>
      <?php if (count($superprods) > 0) : ?>
      <h2 class="text-center"><?= $langs->trans("SUPERPROD_SUPERVISION") ?></h1>
      <hr/>
      
      <div class="container">
        <div class="row">
            <?php foreach ($superprods as $superprod) : ?>
            <?php 
                // On cherche le nom du client
                $societe = new Societe($db);
                $societe->fetch($superprod->fk_soc);
                
                // Label de laffaire
                $labelAffaire = '';
                if (!empty($superprod->fk_productcase)) {
                    $productcase = new Productcase($db);
                    $productcase->fetch($superprod->fk_productcase);
                    $labelAffaire = $productcase->label;
                }
                // On vérifie le label produit
                if (empty($labelAffaire)) {
                    $line = new OrderLine($db);
                    $line->fetch($superprod->fk_commandedet);
                    if (!empty($line->fk_product)) {
                        $product = new Product($db);
                        $product->fetch($line->fk_product);
                        $labelAffaire = $product->ref." : ".$product->label;
                    }
                    if (empty($labelAffaire)) {
                        $labelAffaire = $line->desc;
                    }
                }
            ?>
            <div class="col-4 mb-3">
                <a href="./supervision_detail.php?rowid=<?= $superprod->rowid ?>"  class="d-block w-100 h-100 p-2 border border-<?= $borders[$superprod->priority] ?> rounded affairelink" style="border-width: thick!important;">
                    <h4 class="text-dark">
                        <i class="fas fa-wrench text-muted"></i>
                        Affaire <?= $superprod->num_case ?>  <span style="font-size:17px;margin-left:25%;">Qté <?= $superprod->qty ?></span>
                        <span class="border border-dark float-right bg-<?= $backgrounds[$superprod->fk_status] ?> d-inline-block" style="width:20px;height:20px;"></span>
                    </h4>  
                    <hr/>
                    <div class="text-dark">
                        <?php if (!empty($labelAffaire)) : ?>
                            <strong><?= $labelAffaire ?></strong>
                            <hr/>
                        <?php endif ?>
                        <strong><?= $langs->trans("CLIENT") ?> : </strong><?= $societe->nom ?>
                        <span class="float-right"><strong><?= $langs->trans("PRIO") ?> : </strong><?= $superprod->priority ?></span>
                        <br/>
                        <strong><?= $langs->trans("TOTALTIMEPLANNED") ?> : </strong><?= $superprod->time_prep_planned + $superprod->time_planned ?> Min
                        <br/>
                        
                        <strong><?= $langs->trans("DELIVERYPLANNED") ?> : </strong><?= dol_print_date($db->jdate($superprod->delivery_date), 'day') ?>
                    </div>
                </a>
            </div>
            <?php endforeach ?>
        </div>
      </div>
      <?php else : ?>
      <h2 class="text-center"><?= $langs->trans("PRODUCTIONEMPTY") ?></h1>
      <hr/>
      <?php endif ?>

    <!-- Optional JavaScript -->
    <!-- jQuery first, then Popper.js, then Bootstrap JS -->
    <script src="https://code.jquery.com/jquery-3.2.1.slim.min.js" integrity="sha384-KJ3o2DKtIkvYIK3UENzmM7KCkRr/rE9/Qpg6aAZGJwFDMVNA/GpGFF93hXpG5KkN" crossorigin="anonymous"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.12.9/umd/popper.min.js" integrity="sha384-ApNbgh9B+Y1QKtv3Rn7W3mgPxhU9K/ScQsAP7hUibX39j7fakFPskvXusvfa0b4Q" crossorigin="anonymous"></script>
    <script src="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0/js/bootstrap.min.js" integrity="sha384-JZR6Spejh4U02d8jOt6vLEHfe/JQGiRRSQQxSfFWpi1MquVdAyjUar5+76PVCmYl" crossorigin="anonymous"></script>
  </body>
</html>