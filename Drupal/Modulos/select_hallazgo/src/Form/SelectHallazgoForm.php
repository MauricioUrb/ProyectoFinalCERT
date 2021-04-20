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
+ Descripción: Formulario para asignar uno o más hallazgos a un sitio en una revisión.
*/
class SelectHallazgoForm extends FormBase{
  /*
  + Descripción: Función para asignar id del formulario
  + Sin parámetros
  */
  public function getFormId(){
    return 'select_hallazgo_form';
  }
  /*
  + Descripción: Función para construir el formulario. Se valida al inicio que se tienen permisos para visualizar el formulario.
  + Parámetros:
  +   - $form: arreglo de formulario de Drupal | Tipo: array, Default: NA |
  +   - $form_state: estado de los formularios creados de Drupal | Tipo: FormStateInterface, Default: NA |
  +   - $rev_id: Id de revisión | Tipo: int, Default: NULL |
  +   - $id_rev_sitio: id_rev_sitio (llave primaria de la tabla que relaciona en número de revisión con el id del sitio) | Tipo: int, Default: NULL |
  +   - $seg: booleano que indica si es revisión de seguimiento | Tipo: bool, Default: 
  */
  public function buildForm(array $form, FormStateInterface $form_state, $rev_id = NULL, $id_rev_sitio = NULL, $seg = NULL){
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
    global $hall_arr;
    $form['id_principal'] = array('#type' => 'hidden', '#value' => $id_rev_sitio);
    $form['regresar'] = array('#type' => 'hidden', '#value' => $rev_id);
    $form['rS'] = array('#type' => 'hidden', '#value' => $seg);
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
    $form['nombre_hall'] = array(
      '#type' => 'textfield',
      '#title' => t('Filtrar por nombre:'),
      '#size' => 100,
      '#maxlength' => 100,
      '#default_value' => $form_state->getValue(['nombre_hall']) ? $form_state->getValue(['nombre_hall']) : '',
    );
    //Filtros
    $filtro = false;
    if($form_state->getValue(['nombre_hall'])){$filtro = true;}
    $form['submitF'] = array(
      '#type' => 'submit',
      '#value' => t('Filtrar'),
      '#name' => "filtrar",
    );
    //Se revisa si el activo ya contiene algún hallazgo
    if(!$seg){
      $select = Database::getConnection()->select('revisiones_hallazgos', 'h');
      $select->fields('h', array('id_hallazgo'));
      $select->condition('id_rev_sitio',$id_rev_sitio);
      $id_hallz = $select->execute()->fetchCol();
    }else{
      //Se obtiene el id_rev_sitio de 
      $select = Database::getConnection()->select('revisiones_sitios', 's');
      $select->join('revisiones','r','s.id_revision = r.id_seguimiento');
      $select->fields('s', array('id_rev_sitio'));
      $select->condition('r.id_revision',$rev_id);
      $id_rev_sitioN = $select->execute()->fetchCol();
      //Ahora sí se obtienen los hallazgos que tiene
      $select = Database::getConnection()->select('revisiones_hallazgos', 'h');
      $select->fields('h', array('id_hallazgo'));
      $select->condition('id_rev_sitio',$id_rev_sitioN[0]);
      $id_hallz = $select->execute()->fetchCol();
    }

    $select = Database::getConnection()->select('hallazgos', 'h');
    $select->fields('h', array('nombre_hallazgo_vulnerabilidad'));
    if(sizeof($id_hallz)){$select->condition('id_hallazgo',$id_hallz,'NOT IN');}
    $select->orderBy('nombre_hallazgo_vulnerabilidad');
    $hallazgosT = $select->execute()->fetchCol();
    $contador = 1;
    foreach ($hallazgosT as $value) {
      $hallazgos[$contador] = $value;
      $contador++;
    }
    $hall_arr = $hallazgos;

    if($form_state->getValue(['nombre_hall'])){
      $filtro = true;
      $ultima = array();
      foreach ($hallazgos as $nombre) {
        if(preg_match("/".$form_state->getValue(['nombre_hall'])."/", $nombre)){
          array_push($ultima, $nombre);
        }
      }
      $hallazgos = $ultima;
    }

    $form['hallazgos'] = array(
      '#type' => 'checkboxes',
      '#title' => t('Selecciona los hallazgos a agregar:'),
      '#options' => $hallazgos,
    );
    
    //Boton para enviar el formulario
    $form['submitG'] = array(
      '#type' => 'submit',
      '#value' => t('Guardar'),
      '#name' => "guardar",
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
  + Descripción: Función para validar los datos proporcionados por el usuario.
  + Parámetros:
  +   - $form: arreglo de formulario de Drupal | Tipo: array, Default: NA |
  +   - $form_state: estado de los formularios creados de Drupal | Tipo: FormStateInterface, Default: NA |
  */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $button_clicked = $form_state->getTriggeringElement()['#name'];
    if($button_clicked == "guardar"){
      $tmp = '';
      $id_hall = $form_state->getValue(['hallazgos']);
      foreach($id_hall as $valores){ $tmp .= $valores.'-'; }
      $valores = explode('-',$tmp);
      while(end($valores) == 0 && sizeof($valores)){$tmp = array_pop($valores);}
      if(!sizeof($valores)){
        $form_state->setErrorByName("hallazgos", "Debes de seleccionar al menos un hallazgo.");
      }
    }
  }
  /*
  + Descripción: Función para mandar los datos proporcionados por el usuario y registrarlos en la base de datos.
  + Parámetros:
  +   - $form: arreglo de formulario de Drupal | Tipo: array, Default: NA |
  +   - $form_state: estado de los formularios creados de Drupal | Tipo: FormStateInterface, Default: NA |
  */
  public function submitForm(array &$form, FormStateInterface $form_state){
    global $hall_arr;
    //global $id_principal;
    global $regresar;
    global $rS;
    $id_principal = $form_state->getValue(['id_principal']);
    $regresar = $form_state->getValue(['regresar']);
    $rS = $form_state->getValue(['rS']);
    $button_clicked = $form_state->getTriggeringElement()['#name'];
    if($button_clicked == "filtrar"){
      //Recargar con el filtro
      $form_state->setRebuild();
    }else{
      $mensaje = 'Hallazgo agregado a la revision.';
      Database::setActiveConnection('drupaldb_segundo');
      $connection = Database::getConnection();
      //Obtener el id_hallazgo
      $tmp = '';
      $id_hall = $form_state->getValue(['hallazgos']);
      foreach($id_hall as $valores){ $tmp .= $valores.'-'; }
      $valores = explode('-',$tmp);
      while(end($valores) == 0 && sizeof($valores)){$tmp = array_pop($valores);}
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
            'id_rev_sitio' => $form_state->getValue(['id_principal']),
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
            'id_revision' => $regresar,
            'id_estatus' => 2,
            'fecha' => $fecha,
          ))
          ->execute();
      }
      Database::setActiveConnection();//*/
      $messenger_service = \Drupal::service('messenger');
      $messenger_service->addMessage($mensaje);
      if(!$rS){
    	 $form_state->setRedirectUrl(Url::fromRoute('edit_revision.content', array('rev_id' => $regresar)));
      }else{
       $form_state->setRedirectUrl(Url::fromRoute('edit_seguimiento.content', array('rev_id' => $regresar)));
      }
    }
  }
}
