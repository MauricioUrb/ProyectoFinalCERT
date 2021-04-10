<?php
/*
 * @file
 * Contains \Drupal\delete_hallazgo_revision\Form\DeleteHallazgoRevisionForm
 */
namespace Drupal\delete_hallazgo_revision\Form;
use Drupal\Core\Database\Database;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\Core\Link;

/*
+ Descripción: Formulario para borrar un hallazgo de un sitio en una revisión.
*/
class DeleteHallazgoRevisionForm extends FormBase{
  /*
  + Descripción: Función para asignar id del formulario
  + Sin parámetros
  */
  public function getFormId(){
    return 'delete_hallazgo_revision_form';
  }
  /*
  + Descripción: Función para construir el formulario. Se valida al inicio que se tienen permisos para visualizar el formulario.
  + Parámetros:
  +   - $form: arreglo de formulario de Drupal | Tipo: array, Default: NA |
  +   - $form_state: estado de los formularios creados de Drupal | Tipo: FormStateInterface, Default: NA |
  +   - $rev_id: id de revisión | Tipo: int, Default: NULL |
  +   - $id_rev_sitio: id_rev_sitio (llave primaria de la tabla que relaciona en número de revisión con el id del sitio) | Tipo: int, Default: NULL |
  +   - $hall_id: id del hallazgo | Tipo: int, Default: NULL |
  +   - $rsh: id_rev_sitio_hall | Tipo: int, Default: NULL |
  +   - $seg: booleano que indica si es revisión de seguimiento | Tipo: bool, Default: 
  */
  public function buildForm(array $form, FormStateInterface $form_state, $rev_id = NULL, $id_rev_sitio = NULL, $hall_id = NULL, $rsh = NULL, $seg = NULL){
    //Comprobación de que el usuario loggeado tiene permiso de ver esta revision
    Database::setActiveConnection('drupaldb_segundo');
    $connection = Database::getConnection();
    $select = Database::getConnection()->select('revisiones_asignadas', 'r');
    $select->fields('r', array('id_usuario'));
    $select->condition('id_revision',$rev_id);
    $results = $select->execute()->fetchCol();
    //estatus_revision
    $select = Database::getConnection()->select('actividad', 'a');
    $select->addExpression('MAX(id_estatus)','actividad');
    $select->condition('id_revision',$rev_id);
    $estatus = $select->execute()->fetchCol();
    Database::setActiveConnection();
    if (!in_array(\Drupal::currentUser()->id(), $results) || $estatus[0] > 2){
      return array('#markup' => "No tienes permiso para ver estos formularios.",);
    }
    global $id;
    $id = $rsh;
    global $id_principal;
    $id_principal = $id_rev_sitio;
    global $regresar;
    $regresar = $rev_id;
    global $id_hall;
    $id_hall = $hall_id;
    global $rS;
    $rS = $seg;
    //Consulta de la URL del sitio para imprimirlo
    Database::setActiveConnection('drupaldb_segundo');
    $connection = Database::getConnection();
    $select = Database::getConnection()->select('revisiones_sitios', 'r');
    $select->join('sitios',"s","r.id_sitio = s.id_sitio");
    $select->fields('s', array('url_sitio'));
    $select->condition('id_rev_sitio',$id_rev_sitio);
    $activo = $select->execute()->fetchCol();
    $form['sitio'] = array(
      '#type' => 'item',
      '#title' => t('Activo'),
      '#markup' => $activo[0],
    );
    $select = Database::getConnection()->select('hallazgos', 'h');
    $select->fields('h', array('nombre_hallazgo_vulnerabilidad'));
    $select->condition('id_hallazgo',$hall_id);
    $nombre_hallazgo = $select->execute()->fetchCol();
    $form['hallazgos'] = array(
      '#type' => 'item',
      '#title' => t('Hallazgo:'),
      '#markup' => $nombre_hallazgo[0],
    );
    //Se obtienen valores si ya existen
    $select = Database::getConnection()->select('revisiones_hallazgos', 'h');
    $select->fields('h', array('descripcion_hall_rev'));
    $select->fields('h', array('recursos_afectador'));
    $select->fields('h', array('impacto_hall_rev'));
    $select->fields('h', array('cvss_hallazgos'));
    $select->condition('id_rev_sitio_hall',$rsh);
    $results = $select->execute();
    Database::setActiveConnection();
    $descripcion_hall_rev = '';
    $recursos_afectador = '';
    $impacto_hall_rev = '';
    $cvss_hallazgos = '';
    foreach ($results as $result) {
      $descripcion_hall_rev = $result->descripcion_hall_rev;
      $recursos_afectador = $result->recursos_afectador;
      $impacto_hall_rev = $result->impacto_hall_rev;
      $cvss_hallazgos = $result->cvss_hallazgos;
    }
    $form['descripcion'] = array(
      '#type' => 'item',
      '#title' => t('Description'),
      '#markup' => $descripcion_hall_rev,
    );
    $form['recursos'] =array(
      '#type' => 'item',
      '#title' => 'Recursos afectados',
      '#markup' => $recursos_afectador,
    );
    $form['impacto'] =array(
      '#type' => 'item',
      '#title' => 'Impacto',
      '#markup' => $impacto_hall_rev,
    );
    $form['cvss'] = array(
      '#type' => 'item',
      '#title' => 'CVSS',
      '#markup' => $cvss_hallazgos,
    );
    //Boton para enviar el formulario
    $form['submit'] = array(
      '#type' => 'submit',
      '#value' => t('Borrar hallazgo'),
    );
    if(!$seg){
      $url = Url::fromRoute('edit_revision.content', array('rev_id' => $rev_id));
    }else{
      $url = Url::fromRoute('edit_seguimiento.content', array('rev_id' => $rev_id));
    }
    $project_link = Link::fromTextAndUrl('Cancelar', $url);
    $project_link = $project_link->toRenderable();
    $project_link['#attributes'] = array('class' => array('button'));
    $form['cancelar'] = array('#markup' => render($project_link),);
    return $form;    
  }
  /*
  + Descripción: Función para mandar los datos proporcionados por el usuario y registrarlos en la base de datos.
  + Parámetros:
  +   - $form: arreglo de formulario de Drupal | Tipo: array, Default: NA |
  +   - $form_state: estado de los formularios creados de Drupal | Tipo: FormStateInterface, Default: NA |
  */
  public function submitForm(array &$form, FormStateInterface $form_state){
    global $id;
    global $id_principal;
    global $regresar;
    global $id_hall;
    global $rS;
    $mensaje = 'Hallazgo eliminado.';
    Database::setActiveConnection('drupaldb_segundo');
    $connection = Database::getConnection();
    $select = Database::getConnection()->select('actividad', 'a');
    $select->fields('a', array('id_actividad'));
    $select->condition('id_revision', $regresar);
    $select->condition('id_estatus', 2);
    $existe = $select->execute()->fetchCol();
    $tmp = getdate();
    $fecha = $tmp['year'].'-'.$tmp['mon'].'-'.$tmp['mday'];
    $update = $connection->update('actividad')
      ->fields(array(
        'fecha' => $fecha,
      ))
      ->condition('id_actividad',$existe[0])
      ->execute();
    $borrar = $connection->delete('revisiones_hallazgos')
      ->condition('id_rev_sitio_hall', $id)
      ->execute();
    Database::setActiveConnection();
    $connection = \Drupal::service('database');
    $elimina = $connection->delete('file_managed')
      ->condition('id_rev_sh', $id)
      ->execute();
    $messenger_service = \Drupal::service('messenger');
    $messenger_service->addMessage($mensaje);
  	if(!$rS){
     $form_state->setRedirectUrl(Url::fromRoute('edit_revision.content', array('rev_id' => $regresar)));
    }else{
     $form_state->setRedirectUrl(Url::fromRoute('edit_seguimiento.content', array('rev_id' => $regresar)));
    }
  }
}