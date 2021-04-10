<?php
/*
 * @file
 * Contains \Drupal\asignar_hallazgos\Form\AsignarHallazgosForm
 */
namespace Drupal\asignar_hallazgos\Form;
use Drupal\Core\Database\Database;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\Core\Link;

/*
+ Descripción: Formulario para editar los datos de los hallazgos asignados a un sitio en una revisión.
*/
class AsignarHallazgosForm extends FormBase{
  /*
  + Descripción: Función para asignar id del formulario
  + Sin parámetros
  */
  public function getFormId(){
    return 'asignar_hallazgos_form';
  }
  /*
  + Descripción: Función para construir el formulario. Se valida al inicio que se tienen permisos para visualizar el formulario.
  + Parámetros:
  +   - $form: arreglo de formulario de Drupal | Tipo: array, Default: NA |
  +   - $form_state: estado de los formularios creados de Drupal | Tipo: FormStateInterface, Default: NA |
  +   - $rev_id: id de revisión | Tipo: int, Default: NULL |
  +   - $id_rev_sitio: id_rev_sitio (llave primaria de la tabla que relaciona en número de revisión con el id del sitio) | Tipo: int, Default: NULL |
  +   - $hall_id: id del hallazgo | Tipo: int, Default: NULL |
  +   - $seg: booleano que indica si es revisión de seguimiento | Tipo: bool, Default: 
  */
  public function buildForm(array $form, FormStateInterface $form_state, $rev_id = NULL, $id_rev_sitio = NULL, $hall_id = NULL, $seg = NULL){
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
    $form['hallazgo'] = array(
      '#type' => 'item',
      '#title' => t('Hallazgo'),
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
      '#type' => 'textarea',
      '#title' => t('Description'),
      '#required' => TRUE,
      '#default_value' => $descripcion_hall_rev,
    );
    $form['recursos'] =array(
      '#type' => 'textarea',
      '#title' => 'Recursos afectados',
      '#required' => TRUE,
      '#default_value' => $recursos_afectador,
    );
    $urlT = Url::fromUri('https://www.first.org/cvss/calculator/3.1');
    $project_linkT = Link::fromTextAndUrl(t('Calculadora CVSS 3.1'), $urlT);
    $project_linkT = $project_linkT->toRenderable();
    $project_linkT['#attributes'] = array('class' => array('button'));
    $form['calculadora'] = array('#markup' => render($project_linkT),);
    $form['impacto'] =array(
      '#type' => 'textfield',
      '#title' => 'Impacto',
      '#required' => TRUE,
      '#size' => 10,
      '#default_value' => $impacto_hall_rev,
      '#description' => 'Escribe sólo el valor numérico.',
    );
    $form['cvss'] = array(
      '#type' => 'textfield',
      '#title' => 'Vector CVSS',
      '#required' => TRUE,
      '#size' => 200,
      '#default_value' => $cvss_hallazgos,
    );
    //Boton para enviar el formulario
    $form['submit'] = array(
      '#type' => 'submit',
      '#value' => t('Guardar'),
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
  public function validateForm(array &$form, FormStateInterface $form_state){
    //Verificacion de la cadena del CVSS
    $formato = preg_split('/AV:[ANLP]\/AC:[HL]\/PR:[NLH]\/UI:[NR]\/S:[UC]\/C:[NLH]\/I:[NLH]\/A:[NLH](\/E:[UPFH])?(\/RL:[OTWU])?(\/RC:[URC])?(\/CR:[LMH])?(\/IR:[LMH])?(\/AR:[LMH])?(\/MAV:[NALP])?(\/MAC:[LH])?(\/MPR:[NLH])?(\/MUI:[NR])?(\/MS:[UC])?(\/MC:[NLH])?(\/MI:[NLH])?(\/MA:[NLH])?/', $form_state->getValue(['cvss']));
    foreach($formato as $valido){
      if($valido != ''){
        $form_state->setErrorByName('cvss','La cadena ingresada de CVSS no tiene el formato correcto.');}
    }
    //Verificacion valor del impacto
    $cvss = explode(".",$form_state->getValue(['impacto']));
    $rango = 0;
    if(sizeof($cvss) != 2){$rango = 1;}
    elseif (strlen($cvss[1]) != 1){$rango = 1;}
    elseif(strlen($cvss[0]) == 2 && $cvss[0] != 10){$rango = 1;}
    elseif(strlen($cvss[0]) == 2 && $cvss[1] != 0){$rango = 1;}
    elseif (strlen($cvss[0]) == 1){
      if($cvss[0] < 0 || $cvss[0] > 9){$rango = 1;}
    }
    elseif($cvss[1] < 0 || $cvss[1] > 9){$rango = 1;}
    if($rango){
      $form_state->setErrorByName('impacto','El valor del impacto sólo puede ir del rango 0.0. a 10.0');
    }
  }
  /*
  + Descripción: Función para mandar los datos proporcionados por el usuario y registrarlos en la base de datos.
  + Parámetros:
  +   - $form: arreglo de formulario de Drupal | Tipo: array, Default: NA |
  +   - $form_state: estado de los formularios creados de Drupal | Tipo: FormStateInterface, Default: NA |
  */
  public function submitForm(array &$form, FormStateInterface $form_state){
    global $id_principal;
    global $regresar;
    global $id_hall;
    global $rS;
    $mensaje = 'Hallazgo agregado a la revision.';
    Database::setActiveConnection('drupaldb_segundo');
    $connection = Database::getConnection();
    //Insercion en la BD
    $mensaje = 'Hallazgo actualizado.';
    $result = $connection->update('revisiones_hallazgos')
      ->fields(array(
        'descripcion_hall_rev' => $form_state->getValue(['descripcion']),
        'recursos_afectador' => $form_state->getValue(['recursos']),
        'impacto_hall_rev' => $form_state->getValue(['impacto']),
        'cvss_hallazgos' => $form_state->getValue(['cvss']),
      ))
      ->condition('id_hallazgo', $id_hall)
      ->condition('id_rev_sitio', $id_principal)
      ->execute();
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
    Database::setActiveConnection();
    $messenger_service = \Drupal::service('messenger');
    $messenger_service->addMessage($mensaje);
  	if(!$rS){
     $form_state->setRedirectUrl(Url::fromRoute('edit_revision.content', array('rev_id' => $regresar)));
    }else{
     $form_state->setRedirectUrl(Url::fromRoute('edit_seguimiento.content', array('rev_id' => $regresar)));
    }
  }
}
