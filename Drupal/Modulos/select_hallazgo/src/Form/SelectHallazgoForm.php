<?php
/*
 * @file
 * Contains \Drupal\select_hallazgo\Form\SelectHallazgoForm
 */
namespace Drupal\select_hallazgo\Form;
use Drupal\Core\Database\Database;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\Core\Link;

/*
 *
 */
class SelectHallazgoForm extends FormBase{
/*
   * (@inheritdoc)
   */
  public function getFormId(){
    return 'select_hallazgo_form';
  }
  /*
   * (@inheritdoc)
   */
  public function buildForm(array $form, FormStateInterface $form_state, $rev_id = NULL, $id_rev_sitio = NULL){
    //Comprobación de que el usuario loggeado tiene permiso de ver esta revision
    Database::setActiveConnection('drupaldb_segundo');
    $connection = Database::getConnection();
    $select = Database::getConnection()->select('revisiones_asignadas', 'r');
    $select->fields('r', array('id_usuario'));
    $select->condition('id_revision',$rev_id);
    $results = $select->execute()->fetchCol();
    //estatus_revision
    $select = Database::getConnection()->select('actividad', 'a');
    $select->fields('a', array('id_estatus'));
    $select->condition('id_revision',$rev_id);
    $estatus = $select->execute()->fetchCol();
    Database::setActiveConnection();
    if (!in_array(\Drupal::currentUser()->id(), $results) || $estatus[0] > 2){
      return array('#markup' => "No tienes permiso para ver estos formularios.",);
    }
    global $id_principal;
    $id_principal = $id_rev_sitio;
    global $regresar;
    $regresar = $rev_id;
    global $hall_arr;
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
      '#title' => t('Agregar hallazgo al activo:'),
      '#markup' => $activo[0],
    );
    //Se revisa si el activo ya contiene algún hallazgo
    $select = Database::getConnection()->select('revisiones_hallazgos', 'h');
    $select->fields('h', array('id_hallazgo'));
    $select->condition('id_rev_sitio',$id_rev_sitio);
    $id_hallz = $select->execute()->fetchCol();

    $select = Database::getConnection()->select('hallazgos', 'h');
    $select->fields('h', array('nombre_hallazgo_vulnerabilidad'));
    if(sizeof($id_hallz)){$select->condition('id_hallazgo',$id_hallz,'NOT IN');}
    $select->orderBy('nombre_hallazgo_vulnerabilidad');
    $hallazgos = $select->execute()->fetchCol();
    $hall_arr = $hallazgos;
    $form['hallazgos'] = array(
      '#type' => 'checkboxes',
      '#title' => t('Selecciona los hallazgos a agregar:'),
      '#options' => $hallazgos,
      '#required' => TRUE,
    );
    
    //Boton para enviar el formulario
    $form['submit'] = array(
      '#type' => 'submit',
      '#value' => t('Guardar'),
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
   * Validacion de los datos ingresados
   */
  public function submitForm(array &$form, FormStateInterface $form_state){
    global $hall_arr;
    global $id_principal;
    global $regresar;
    $mensaje = 'Hallazgo agregado a la revision.';
    Database::setActiveConnection('drupaldb_segundo');
    $connection = Database::getConnection();
    //Obtener el id_hallazgo
    $tmp = '';
    $id_hall = $form_state->getValue(['hallazgos']);
    foreach($id_hall as $valores){ $tmp .= $valores.'-'; }
    $valores = explode('-',$tmp);
    while(end($valores) == 0 && sizeof($valores) > 1){$tmp = array_pop($valores);}
    $tmp = 0;
    foreach($valores as $pos){
      $nombres[$tmp] = $form['hallazgos']['#options'][$pos];
      $tmp++;
    }
    $consulta = Database::getConnection()->select('hallazgos', 'h');
    $consulta->fields('h', array('id_hallazgo'));
    $consulta->fields('h', array('descripcion_hallazgo'));
    $consulta->fields('h', array('nivel_cvss'));
    $consulta->fields('h', array('vector_cvss'));
    $consulta->condition('nombre_hallazgo_vulnerabilidad',$nombres,'IN');
    $datosH = $consulta->execute();
    //Insercion en la BD
    foreach ($datosH as $dato) {
      $impacto = $dato->nivel_cvss;
      preg_match('/[\d]+.\d/', $dato->nivel_cvss, $impacto);
      $result = $connection->insert('revisiones_hallazgos')
        ->fields(array(
          'id_rev_sitio' => $id_principal,
          'id_hallazgo' => $dato->id_hallazgo,
          'descripcion_hall_rev' => $dato->descripcion_hallazgo,
          'recursos_afectador' => '/',
          'impacto_hall_rev' => $impacto[0],
          'cvss_hallazgos' => $dato->vector_cvss,
          'estatus' => 1,
        ))->execute();
    }
    //Se revisa si ya se tiene ese estado, de otro modo, se actualiza
    $select = Database::getConnection()->select('actividad', 'a');
    $select->fields('a', array('id_actividad'));
    $select->condition('id_revision', $regresar);
    $select->condition('id_estatus', 2);
    $existe = $select->execute()->fetchCol();
    $tmp = getdate();
    $fecha = $tmp['year'].'-'.$tmp['mon'].'-'.$tmp['mday'];
    if(sizeof($existe)){
      $update = $connection->update('actividad')
        ->fields(array(
          'fecha' => $fecha,
        ))
        ->condition('id_actividad',$existe[0])
        ->execute();
    }else{
      $update = $connection->insert('actividad')
        ->fields(array(
          'id_revision' => $id_rev,
          'id_estatus' => 2,
          'fecha' => $fecha,
        ))
        ->execute();
    }/*
    $result = $connection->update('actividad')
      ->fields(array(
        'id_estatus' => 2,
      ))
      ->condition('id_revision', $regresar)
      ->execute();//*/
    Database::setActiveConnection();
    $messenger_service = \Drupal::service('messenger');
    $messenger_service->addMessage($mensaje);
  	$form_state->setRedirectUrl(Url::fromRoute('edit_revision.content', array('rev_id' => $regresar)));
  }
}
