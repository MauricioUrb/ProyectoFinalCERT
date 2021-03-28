<?php
/*
 * @file
 * Contains \Drupal\hallazgos_show\Form\HallazgosShowForm
 */
namespace Drupal\hallazgos_show\Form;
use Drupal\Core\Database\Database;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Markup;
use Drupal\Core\Url;
use Drupal\Core\Link;
/*
 *
 */
class HallazgosShowForm extends FormBase{
  /*
   * (@inheritdoc)
   */
  public function getFormId(){
    return 'hallazgos_show_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state){
    if (in_array('coordinador de revisiones', \Drupal::currentUser()->getRoles()) || in_array('pentester', \Drupal::currentUser()->getRoles()) || in_array('auxiliar', \Drupal::currentUser()->getRoles())){    
      //conectar a la otra db
      \Drupal\Core\Database\Database::setActiveConnection('drupaldb_segundo');
      $connection = \Drupal\Core\Database\Database::getConnection();
      //Se selecciona la tabla en modo lectura
      $select = Database::getConnection()->select('hallazgos', 'h');
      //Se especifican las columnas a leer
      $select->fields('h', array('id_hallazgo'))
             ->fields('h', array('nombre_hallazgo_vulnerabilidad'))
             ->fields('h', array('descripcion_hallazgo'))
             ->fields('h', array('solucion_recomendacion_halazgo'))
             ->fields('h', array('referencias_hallazgo'))
             ->fields('h', array('recomendacion_general_hallazgo'))
             ->fields('h', array('nivel_cvss'))
             ->fields('h', array('vector_cvss'))
             ->fields('h', array('enlace_cvss'))
             ->fields('h', array('r_ejecutivo_hallazgo'))
             ->fields('h', array('solucion_corta'));
      $select->orderBy('nombre_hallazgo_vulnerabilidad');
      $select = $select->extend('Drupal\Core\Database\Query\PagerSelectExtender')->limit(15);
      //Se realiza la consulta
      $results = $select->execute();

      $txt = '';
      //se recorren los resultados para después imprimirlos
      foreach ($results as $result){
        $url1 = Url::fromRoute('editar_hallazgos.content', array('id_h' => $result->id_hallazgo));
        $project_link1 = Link::fromTextAndUrl('Editar ', $url1);
        $project_link1 = $project_link1->toRenderable();
        $project_link1['#attributes'] = array('class' => array('button'));

        $url = Url::fromRoute('eliminar_hallazgos.content', array('id_h' => $result->id_hallazgo));
        $project_link = Link::fromTextAndUrl('Eliminar ', $url);
        $project_link = $project_link->toRenderable();
        $project_link['#attributes'] = array('class' => array('button'));

        $txt .= '<br />';
        $rows[$result->id_hallazgo] = [
                  $result->id_hallazgo,
                  $result->nombre_hallazgo_vulnerabilidad,
                  $result->descripcion_hallazgo,
                  $result->solucion_recomendacion_halazgo,
                  $result->referencias_hallazgo,
                  $result->recomendacion_general_hallazgo,
                  $result->nivel_cvss,
                  $result->vector_cvss,
                  $result->enlace_cvss,
                  $result->r_ejecutivo_hallazgo,
                  $result->solucion_corta,
                  //Markup::create('<a href="/node/add/page">Editar</a>'),
                  //Markup::create('<a href="/node/add/page">Eliminar</a>'),
                  render($project_link1),
                  render($project_link),
          ];
      }

      //$form['txt']['#markup'] = $txt;
      //Se asignan titulos a cada columna
      $header = [
        'id' => t('ID'),
        'name' => t('Nombre'),
        'description' => t('Descripcion'),
        'sol' => t('Sol/Recomendación'),
        'ref' => t('Referencias'),
        'recom' => t('Recomendacion'),
        'nivel' => t('Nivel'),
        'vector' => t('Vector'),
        'enlace' => t('Enlace'),
        'r_ejecutivo' => t('Resumen'),
        'sol_corta' => t('Sol Corta'),
        'edit' => t('Editar'),
        'del' => t('Eliminar'),
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
      //regresar a bd la default
      \Drupal\Core\Database\Database::setActiveConnection();
      $form['pager'] = array('#type' => 'pager');

      $url = Url::fromRoute('hallazgos_alta.content', array());
      $project_link = Link::fromTextAndUrl('Agregar un nuevo hallazgo', $url);
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

    //Se selecciona la tabla en modo lectura
    $select = $connection->select('hallazgos', 'h');
    //Se especifican las columnas a leer
    $select->fields('h', array('id_hallazgo'))
	   ->fields('h', array('nombre_hallazgo_vulnerabilidad'))//1
	   ->fields('h', array('descripcion_hallazgo'))//2
	   ->fields('h', array('solucion_recomendacion_halazgo'))//4
	   ->fields('h', array('referencias_hallazgo'))//5
	   ->fields('h', array('recomendacion_general_hallazgo'))//8
	   ->fields('h', array('nivel_cvss'))//10
	   ->fields('h', array('vector_cvss'))//9
	   ->fields('h', array('enlace_cvss'))//6
	   ->fields('h', array('r_ejecutivo_hallazgo'))//7
	   ->fields('h', array('solucion_corta'));//3
    $select->orderBy('nombre_hallazgo_vulnerabilidad');
    //Se realiza la consulta
    $results = $select->execute();

    $valores = array();
    $valores[0] = ["nombre del hallazgo", "descripcion del hallazgo", "solucion corta", "solucion/recomendacion", "referencias", "enlace del cvss", "resumen ejecutivo", "recomendacion general", "vector cvss","criticidad"];
    $cont = 1;
    foreach($results as $r){
	    $valores[$cont] = ["$r->nombre_hallazgo_vulnerabilidad", "$r->descripcion_hallazgo", "$r->solucion_corta", "$r->solucion_recomendacion_halazgo", "$r->referencias_hallazgo", "$r->enlace_cvss", "$r->r_ejecutivo_hallazgo", "$r->recomendacion_general_hallazgo", "$r->vector_cvss", "$r->nivel_cvss"];
	    $cont++;
    }

    $spreadsheet = new Spreadsheet();
    $worksheet = $spreadsheet->getActiveSheet();
    $worksheet->fromArray($valores);

    $date = date("Y-m-d_H-i-s");
    $filename = "$date" . "_hallazgos.csv";

    $writer1 = new \PhpOffice\PhpSpreadsheet\Writer\Csv($spreadsheet);
    $writer1->setSheetIndex(0);   // Select which sheet to export.
    $writer1->save("public://csv_files/hallazgos/export/$filename");

    $response = new RedirectResponse("/sites/default/files/csv_files/hallazgos/export/$filename", 302);
    $response->send();
  }
}
