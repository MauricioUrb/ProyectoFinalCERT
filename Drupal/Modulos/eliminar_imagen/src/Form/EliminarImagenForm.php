<?php
/**
 * @file
 * Contains \Drupal\eliminar_imagen\Form\EliminarImagenForm
 */
namespace Drupal\eliminar_imagen\Form;
use Drupal\Core\Database\Database;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\Core\Link;

/*
+ Descripción: Formulario para borrar una imágen relacionada con un hallazgo en un sitio de una revisión.
*/
class EliminarImagenForm extends FormBase {
  /*
  + Descripción: Función para asignar id del formulario
  + Sin parámetros
  */
  public function getFormId() {
    return 'eliminar_imagen_form';
  }
  /*
  + Descripción: Función para construir el formulario. Se valida al inicio que se tienen permisos para visualizar el formulario.
  + Parámetros:
  +   - $form: arreglo de formulario de Drupal | Tipo: array, Default: NA |
  +   - $form_state: estado de los formularios creados de Drupal | Tipo: FormStateInterface, Default: NA |
  +   - $fid: Llave primaria de la tabla file_managed donde se almacena la imágen | Tipo: int, Default: NULL |
  +   - $rev_id: Id de revisión | Tipo: int, Default: NULL |
  +   - $rsh: id_rev_sitio_hall | Tipo: int, Default: NULL |
  +   - $seg: booleano que indica si es revisión de seguimiento | Tipo: bool, Default: 
  */
  public function buildForm(array $form, FormStateInterface $form_state, $fid = NULL, $rev_id = NULL, $rsh = NULL, $seg = NULL) {
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
    $id = $fid;
    global $id_rev;
    $id_rev = $rev_id;
    global $id_rimg;
    $id_rimg = $rsh;
    global $rS;
    $rS = $seg;

    $txt = '';
    $txt .= 'Confirmar eliminación de registro';
    $txt .= '<br />';
    $txt .= '<br />';

    $form['txt']['#markup'] = $txt;

    $form['submit'] = array(
      '#type' => 'submit',
      '#value' => t('Aceptar'),
    );

    $url = Url::fromRoute('mostrar_imagen.content', array('rev_id' => $rev_id, 'rsh' => $rsh, 'seg' => $seg));
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
  public function submitForm(array &$form, FormStateInterface $form_state) {
	  global $id;
    global $id_rev;
    global $id_rimg;
    global $rS;
	  //se hace la conexion a la base de datos
    $connection = \Drupal::service('database');
	  //se elimina un regitro de la tabla file_managed
	  $elimina = $connection->delete('file_managed')
		  //se agrega la condicion id_sitio
		  ->condition('fid', $id)
		  ->execute();
	  // mostramos el mensaje
    if($elimina!=FALSE){
      $messenger_service = \Drupal::service('messenger');
      $messenger_service->addMessage(t('Se ha actualizado la base de datos'));
    } else {
      $messenger_service = \Drupal::service('messenger');
      $messenger_service->addMessage(t('No existe el registro'));
    }
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
    Database::setActiveConnection();
    $form_state->setRedirectUrl(Url::fromRoute('mostrar_imagen.content', array('rev_id' => $id_rev, 'rsh' => $id_rimg, 'seg' => $rS)));
  }
}
