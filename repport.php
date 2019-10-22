<?php
/* Copyright (C) 2019 ATM Consulting <support@atm-consulting.fr>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

require 'config.php';
dol_include_once('paymentschedule/class/paymentschedule.class.php');

if(empty($user->rights->paymentschedule->read)) accessforbidden();

$langs->load('abricot@abricot');
$langs->load('paymentschedule@paymentschedule');

$nbLine = GETPOST('limit');

$search_nom = GETPOST('Listview_paymentschedulerepport_search_nom', 'alpha');
$search_code_client = GETPOST('Listview_paymentschedulerepport_search_code_client', 'alpha');
$search_code_compta = GETPOST('Listview_paymentschedulerepport_search_code_compta', 'alpha');

$year = date('Y');
$month = date('m');


if ($month < $conf->global->SOCIETE_FISCAL_MONTH_START)
{
    $date_fiscal_start = strtotime(date(($year-1).'-'.str_pad($conf->global->SOCIETE_FISCAL_MONTH_START, 2, '0', STR_PAD_LEFT).'-01 00:00:00'));
}
else
{
    $date_fiscal_start = strtotime(date($year.'-'.str_pad($conf->global->SOCIETE_FISCAL_MONTH_START, 2, '0', STR_PAD_LEFT).'-01 00:00:00'));
}

//$fiscal_year_month = GETPOST('fiscal_year_month');
//if (empty($fiscal_year_month)) $fiscal_year_month = date('Y').'-'.str_pad($conf->global->SOCIETE_FISCAL_MONTH_START, 2, '0', STR_PAD_LEFT)

$object = new PaymentSchedule($db);

$hookmanager->initHooks(array('paymentschedulerepport'));

/*
 * Actions
 */

$parameters=array('type' => 'pca');
$reshook=$hookmanager->executeHooks('doActions', $parameters, $object);    // Note that $action and $object may have been modified by some hooks
if ($reshook < 0) setEventMessages($hookmanager->error, $hookmanager->errors, 'errors');

if (empty($reshook))
{
    // do action from GETPOST ... 
}


/*
 * View
 */

llxHeader('', $langs->trans('PaymentSchedulePcaRepport'), '', '');




$date_fiscal_end = strtotime('+1 year -1 day', $date_fiscal_start);

$date_fiscal_start_pca = strtotime('+1 year', $date_fiscal_start);
$date_fiscal_end_pca = strtotime('+1 year', $date_fiscal_end);

//var_dump(
//    $date_fiscal_start
//    , date('Y-m-d H:i:s', $date_fiscal_start)
//    , date('Y-m-d H:i:s', $date_fiscal_start_pca)
//    , $date_fiscal_end
//    , date('Y-m-d H:i:s', $date_fiscal_end)
//    , date('Y-m-d H:i:s', $date_fiscal_end_pca)
//);exit;

$sql = 'SELECT s.rowid AS fk_soc, s.nom, s.code_client, s.code_compta, SUM(psd.amount_ttc) AS amount_pca';

// Add fields from hooks
$parameters=array('sql' => $sql);
$reshook=$hookmanager->executeHooks('printFieldListSelect', $parameters, $object);    // Note that $action and $object may have been modified by hook
$sql.=$hookmanager->resPrint;

$sql.= ' FROM '.MAIN_DB_PREFIX.'societe s ';

// TODO faire une jointure pour que ce soit uniquement les factures associées à un contrat de type S+
$sql.= ' INNER JOIN '.MAIN_DB_PREFIX.'facture f ON (f.fk_soc = s.rowid)';
$sql.= ' INNER JOIN '.MAIN_DB_PREFIX.'paymentschedule ps ON (ps.fk_facture = f.rowid AND ps.status = 1)'; // status = 1 : pour validé
//$sql.= ' INNER JOIN '.MAIN_DB_PREFIX.'paymentscheduledet psd ON (psd.fk_payment_schedule = ps.rowid AND psd.date_demande >= \''.$db->idate($date_fiscal_start_pca).'\')';
$sql.= ' INNER JOIN '.MAIN_DB_PREFIX.'paymentscheduledet psd ON (psd.fk_payment_schedule = ps.rowid)';

$sql.= ' WHERE 1=1';

if (!empty($search_nom)) $sql.= natural_search('nom', $search_nom);
if (!empty($search_code_client)) $sql.= ' AND code_client LIKE \'%'.$db->escape($search_code_client).'%\'';
if (!empty($search_code_compta)) $sql.= ' AND code_compta LIKE \'%'.$db->escape($search_code_compta).'%\'';
//$sql.= ' AND t.entity IN ('.getEntity('PaymentSchedule', 1).')';
//if ($type == 'mine') $sql.= ' AND t.fk_user = '.$user->id;

// Add where from hooks
$parameters=array('sql' => $sql);
$reshook=$hookmanager->executeHooks('printFieldListWhere', $parameters, $object);    // Note that $action and $object may have been modified by hook
$sql.=$hookmanager->resPrint;

$sql.= ' GROUP BY s.rowid';
// Add group by from hooks
$parameters=array('sql' => $sql);
$reshook=$hookmanager->executeHooks('printFieldListGroupBy', $parameters, $object);    // Note that $action and $object may have been modified by hook
$sql.=$hookmanager->resPrint;


$resql = $db->query($sql);

$TData = array();

if ($resql)
{
    while ($obj = $db->fetch_object($resql))
    {
        $TData[] = (array) $obj;
    }
}
else
{
    dol_print_error($db);
    exit;
}

$formcore = new TFormCore($_SERVER['PHP_SELF'], 'form_list_paymentschedule', 'GET');

//$html_select_fiscal = '<select name="test"><options value=""></options></select>';
$html_select_fiscal = '';

if (empty($nbLine)) $nbLine = !empty($user->conf->MAIN_SIZE_LISTE_LIMIT) ? $user->conf->MAIN_SIZE_LISTE_LIMIT : $conf->global->MAIN_SIZE_LISTE_LIMIT;

$r = new Listview($db, 'paymentschedulerepport');
echo $r->renderArray($db, $TData, array(
        'limit'=>array(
            'nbLine' => $nbLine
        )
        ,'list' => array(
            'title' => $langs->trans('PaymentSchedulePcaRepportList')
            ,'image' => 'title_generic.png'
            ,'picto_precedent' => '<'
            ,'picto_suivant' => '>'
            ,'noheader' => 0
            ,'messageNothing' => $langs->trans('NoPaymentSchedule')
            ,'picto_search' => img_picto('', 'search.png', '', 0)
            ,'head_search' => '' // '<div class="divsearchfield">'.$langs->trans('PaymentScheduleSearchDate').' '.$html_select_fiscal.'</div>'
            ,'haveTotal' => true
        )
        ,'link' => array(
            'nom' => '<a href="'.dol_buildpath('societe/card.php', 1).'?socid=@fk_soc@">@val@</a>'
        )
        ,'type' => array(
            'amount_pca' => 'money' // [datetime], [hour], [money], [number], [integer]
        )
        ,'search' => array(
            'nom' => array('search_type' => true, 'table' => array('s'), 'field' => array('nom'))
            ,'code_client' => array('search_type' => true, 'table' => array('s'), 'field' => array('code_client'))
            ,'code_compta' => array('search_type' => true, 'table' => array('s'), 'field' => array('code_compta'))
        )
        ,'hide' => array(
            'rowid' // important : rowid doit exister dans la query sql pour les checkbox de massaction
        )
        ,'title'=>array(
            'nom' => $langs->trans('Company')
            ,'code_client' => $langs->trans('CustomerCode')
            ,'code_compta' => $langs->trans('CustomerAccountancyCodeShort')
            ,'amount_pca' => $langs->trans('AmountTTC')
        )
        ,'position' => array(
            'text-align' => array(
                'amount_pca' => 'right'
            )
        )
        ,'math' => array(
            'amount_pca' => array(0 => 'sum', 1 => 'amount_pca')
        )
    )
);

//echo $r->render($sql, array(
//    'view_type' => 'list' // default = [list], [raw], [chart]
//    ,'allow-fields-select' => true
//    ,'limit'=>array(
//        'nbLine' => $nbLine
//    )
//    ,'list' => array(
//        'title' => $langs->trans('PaymentScheduleList')
//        ,'image' => 'title_generic.png'
//        ,'picto_precedent' => '<'
//        ,'picto_suivant' => '>'
//        ,'noheader' => 0
//        ,'messageNothing' => $langs->trans('NoPaymentSchedule')
//        ,'picto_search' => img_picto('', 'search.png', '', 0)
//        ,'massactions'=>array(
//            'yourmassactioncode'  => $langs->trans('YourMassActionLabel')
//        )
//    )
//    ,'subQuery' => array()
//    ,'link' => array()
//    ,'type' => array(
//        'date_creation' => 'date' // [datetime], [hour], [money], [number], [integer]
//        ,'tms' => 'date'
//    )
//    ,'search' => array(
//        'date_creation' => array('search_type' => 'calendars', 'allow_is_null' => true)
//        ,'tms' => array('search_type' => 'calendars', 'allow_is_null' => false)
//        ,'ref' => array('search_type' => true, 'table' => 't', 'field' => 'ref')
//        ,'label' => array('search_type' => true, 'table' => array('t', 't'), 'field' => array('label')) // input text de recherche sur plusieurs champs
//        ,'status' => array('search_type' => PaymentSchedule::$TStatus, 'to_translate' => true) // select html, la clé = le status de l'objet, 'to_translate' à true si nécessaire
//    )
//    ,'translate' => array()
//    ,'hide' => array(
//        'rowid' // important : rowid doit exister dans la query sql pour les checkbox de massaction
//    )
//    ,'title'=>array(
//        'ref' => $langs->trans('Ref.')
//        ,'label' => $langs->trans('Label')
//        ,'date_creation' => $langs->trans('DateCre')
//        ,'tms' => $langs->trans('DateMaj')
//    )
//    ,'eval'=>array(
//        'ref' => '_getObjectNomUrl(\'@rowid@\', \'@val@\')'
////		,'fk_user' => '_getUserNomUrl(@val@)' // Si on a un fk_user dans notre requête
//    )
//));

$parameters=array('sql'=>$sql);
$reshook=$hookmanager->executeHooks('printFieldListFooter', $parameters, $object);    // Note that $action and $object may have been modified by hook
print $hookmanager->resPrint;

$formcore->end_form();

llxFooter('');
$db->close();

/**
 * TODO remove if unused
 */
function _getObjectNomUrl($id, $ref)
{
    global $db;

    $o = new PaymentSchedule($db);
    $res = $o->fetch($id, false, $ref);
    if ($res > 0)
    {
        return $o->getNomUrl(1);
    }

    return '';
}

/**
 * TODO remove if unused
 */
function _getUserNomUrl($fk_user)
{
    global $db;

    $u = new User($db);
    if ($u->fetch($fk_user) > 0)
    {
        return $u->getNomUrl(1);
    }

    return '';
}