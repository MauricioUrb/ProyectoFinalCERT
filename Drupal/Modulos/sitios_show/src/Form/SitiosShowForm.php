<?php
/*
 * @file
 * Contains \Drupal\sitios_show\Form\SitiosShowForm
 */
namespace Drupal\sitios_show\Form;
use Drupal\Core\Database\Database;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Markup;
use Drupal\Core\Url;
use Drupal\Core\Link;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\IOFactory;

use Drupal\node\Entity\Node;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

use Drupal\Core\Form\File;
use Drupal\Component\Utility\Environment;
use Drupal\Core\File\FileSystemInterface;

/*
 *
 */
class SitiosShowForm extends FormBase{

  public function getFormId(){
    return 'sitios_show_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state){
    if (in_array('coordinador de revisiones', \Drupal::currentUser()->getRoles()) || in_array('auxiliar', \Drupal::currentUser()->getRoles())){
      //conectar a la otra db
      \Drupal\Core\Database\Database::setActiveConnection('drupaldb_segundo');
      $connection = \Drupal\Core\Database\Database::getConnection();
      //se selecciona la base de datos
      $select = Database::getConnection()->select('dependencias', 'd');
      //Se hace un join con tablas necesarias
      $select ->join('dependencias_sitios', 'ds', 'd.id_dependencia = ds.id_dependencia');
      $select ->join('sitios', 's', 's.id_sitio = ds.id_sitio');
      $select ->join('ip_sitios', 'ips', 's.id_sitio = ips.id_sitio');
      $select ->join('dir_ip', 'ip', 'ip.id_ip = ips.id_ip');
      //Se especifican las columnas a leer
      $select->fields('s', array('id_sitio', 'descripcion_sitio', 'url_sitio'))
             ->fields('d', array('nombre_dependencia', 'id_dependencia' ))
             ->fields('ip', array('dir_ip_sitios', 'id_ip'));
      //evitar repetidos
      $select->distinct();
      $select->condition('activo',1);
      $select->orderBy('url_sitio');
      $select = $select->extend('Drupal\Core\Database\Query\PagerSelectExtender')->limit(15);
      //Se realiza la consulta
      $results = $select->execute();

      $txt = '';
      //se recorren los resultados para después imprimirlos
      foreach ($results as $result){

          $url1 = Url::fromRoute('editar_sitios.content', array('id_s' => $result->id_sitio,
  							'id_ip' => $result->id_ip,
                                                          'id_dep' => $result->id_dependencia));
          $project_link1 = Link::fromTextAndUrl('Editar ', $url1);
          $project_link1 = $project_link1->toRenderable();
          $project_link1['#attributes'] = array('class' => array('button'));

          $url = Url::fromRoute('eliminar_sitios.content', array('id_s' => $result->id_sitio));
          $project_link = Link::fromTextAndUrl('Eliminar ', $url);
          $project_link = $project_link->toRenderable();
          $project_link['#attributes'] = array('class' => array('button'));

          $txt .= '<br />';
          $rows[] = [
                  $result->id_sitio,
                  $result->descripcion_sitio,
                  $result->url_sitio,
                  $result->nombre_dependencia,
                  $result->dir_ip_sitios,
  //              Markup::create('<a href="/hallazgos/alta">Editar</a>'),
  //              Markup::create('<a href="/node/add/page">Eliminar</a>'),
                  render($project_link1),
                  render($project_link),
          ];
      }
      $form['txt']['#markup'] = $txt;
      //Se asignan titulos a cada columna
      $header = [
        'id' => t('ID'),
        'title' => t('Descripcion'),
        'url' => t('URL'),
        'dep' => t('Dependencia'),
        'ip' => t('IP'),
        'action1' => t('Editar'),
        'action2' => t('Eliminar'),
      ];
      //se construye la tabla para mostrar
      $build['table'] = [
        '#type' => 'table',
        '#header' => $header,
        '#rows' => $rows,
        '#empty' => t('Nada para mostrar.'),
      ];

      $form = [
        '#type' => '#markup',
        '#markup' => render($build)
      ];
      $form['pager'] = array('#type' => 'pager');

      $url = Url::fromRoute('sitios_alta.content', array());
      $project_link = Link::fromTextAndUrl('Agregar un sitio nuevo', $url);
      $project_link = $project_link->toRenderable();
      $project_link['#attributes'] = array('class' => array('button'));
      $form['boton'] = array('#markup' => render($project_link),);
      
      $form["button"] = array(	  
        '#type' => 'submit',  
        '#value' => t('Exportar a CSV'),
        '#name' => "export",
        '#button_type' => 'primary',    
      );

      return $form;
    }
    else{
      return array('#markup' => "No tienes permiso para ver estos formularios.",);
    }
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    //$messenger_service = \Drupal::service('messenger');
    //$messenger_service->addMessage(t('The form is working.'));

    //conectar a la otra db
    \Drupal\Core\Database\Database::setActiveConnection('drupaldb_segundo');
    $connection = \Drupal\Core\Database\Database::getConnection();

    //se selecciona la base de datos
    $select = $connection->select('dependencias', 'd');
    //Se hace un join con tablas necesarias
    $select ->join('dependencias_sitios', 'ds', 'd.id_dependencia = ds.id_dependencia');
    $select ->join('sitios', 's', 's.id_sitio = ds.id_sitio');
    $select ->join('ip_sitios', 'ips', 's.id_sitio = ips.id_sitio');
    $select ->join('dir_ip', 'ip', 'ip.id_ip = ips.id_ip');
    //Se especifican las columnas a leer
    $select->fields('s', array('id_sitio', 'descripcion_sitio', 'url_sitio'))
	   ->fields('d', array('nombre_dependencia', 'id_dependencia' ))
	   ->fields('ip', array('dir_ip_sitios', 'id_ip'));
    //evitar repetidos
    $select->distinct();
    $select->orderBy('url_sitio');
    //Se realiza la consulta
    $results = $select->execute();

    $valores = array();
    $valores[0] = ["Descripcion", "URL", "IP", "Dependencia"];
    $cont = 1;
    foreach($results as $result){
	    $valores[$cont] = ["$result->descripcion_sitio", "$result->url_sitio", "$result->dir_ip_sitios", "$result->nombre_dependencia"];
	    $cont++;
    }

    $spreadsheet = new Spreadsheet();
    $worksheet = $spreadsheet->getActiveSheet();
    $worksheet->fromArray($valores);

    $date = date("Y-m-d_H-i-s");
    $filename = "$date" . "_sitios.csv";

    $writer1 = new \PhpOffice\PhpSpreadsheet\Writer\Csv($spreadsheet);
    $writer1->setSheetIndex(0);   // Select which sheet to export.
    $writer1->save("public://csv_files/sitios/export/$filename");

    $response = new RedirectResponse("/sites/default/files/csv_files/sitios/export/$filename", 302);
    $response->send();
  }
}
