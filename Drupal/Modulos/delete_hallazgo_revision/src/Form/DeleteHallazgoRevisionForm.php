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
 *
 */
class DeleteHallazgoRevisionForm extends FormBase{
/*
   * (@inheritdoc)
   */
  public function getFormId(){
    return 'delete_hallazgo_revision_form';
  }
  /*
   * (@inheritdoc)
   */
  public function buildForm(array $form, FormStateInterface $form_state, $rev_id = NULL, $id_rev_sitio = NULL, $hall_id = NULL, $rsh = NULL){
    //Comprobación de que el usuario loggeado tiene permiso de ver esta revision
    Database::setActiveConnection('drupaldb_segundo');
    $connection = Database::getConnection();
    $select = Database::getConnection()->select('revisiones_asignadas', 'r');
    $select->fields('r', array('id_usuario'));
    $select->condition('id_revision',$rev_id);
    $select->condition('seguimiento', false);
    $results = $select->execute()->fetchCol();
    //estatus_revision
    $select = Database::getConnection()->select('revisiones', 'r');
    $select->fields('r', array('id_estatus'));
    $select->condition('id_revision',$rev_id);
    $estatus = $select->execute()->fetchCol();
    Database::setActiveConnection();
    if (!in_array(\Drupal::currentUser()->id(), $results) || !in_array('pentester', \Drupal::currentUser()->getRoles()) || $estatus[0] > 2){
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
    $select->condition('id_rev_sitio',$id_rev_sitio);
    $select->condition('id_hallazgo',$hall_id);
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
    $url = Url::fromRoute('edit_revision.content', array('rev_id' => $rev_id));
    $project_link = Link::fromTextAndUrl('Cancelar', $url);
    $project_link = $project_link->toRenderable();
    $project_link['#attributes'] = array('class' => array('button'));
    $form['cancelar'] = array('#markup' => render($project_link),);
    return $form;    
  }
  /*
   * (@inheritdoc)
   */
  public function submitForm(array &$form, FormStateInterface $form_state){
    global $id;
    global $id_principal;
    global $regresar;
    global $id_hall;
    $mensaje = 'Hallazgo eliminado.';
    Database::setActiveConnection('drupaldb_segundo');
    $connection = Database::getConnection();
    $borrar = $connection->delete('revisiones_hallazgos')
      ->condition('id_rev_sitio', $id_principal)
      ->condition('id_hallazgo', $id_hall)
      ->execute();
    Database::setActiveConnection();
    $connection = \Drupal::service('database');
    //se elimina un regitro de la tabla file_managed
    $elimina = $connection->delete('file_managed')
      //se agrega la condicion id_sitio
      ->condition('id_rev_sh', $id)
      ->execute();
    $messenger_service = \Drupal::service('messenger');
    $messenger_service->addMessage($mensaje);
  	$form_state->setRedirectUrl(Url::fromRoute('edit_revision.content', array('rev_id' => $regresar)));
  }
}