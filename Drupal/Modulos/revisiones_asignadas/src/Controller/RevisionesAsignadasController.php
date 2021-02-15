<?php

namespace Drupal\revisiones_asignadas\Controller;
use Drupal\Core\Url;
use Drupal\Core\Link;
use Drupal\Core\Database\Database;

class RevisionesAsignadasController {
  public function revisiones(){
    //Consulta de las revisiones que tiene el usuario
    $user = \Drupal::currentUser()->id();

    //Coneccion a la BD
    //Se selecciona la tabla en modo lectura
    $usuario = Database::getConnection()->select('revisiones_asignadas', 'r');
    //Se especifican las columnas a leer
    $usuario->fields('r', array('id_revisiones'));
    //Consulta para determinar las revisiones asignadas a este usuario logueado
    $usuario->condition('id_usuarios',$user);
    //Ya se tienen las revisiones asignadas a este usuario logueado
    $revisiones = $usuario->execute()->fetchCol();

    if(!empty($revisiones)){
      foreach ($revisiones as $rev){
        //redireccion a modulo temporal
        $url = Url::fromRoute('asignacion_revisiones.content', array('rev_id' => $rev));
        /*
        En el routing.yml en el path se agrega al final /{rev_id}, dependiendo de el arreglo y nombre que se le pase
        en el src/Controller/Archivo.php o src/Form/Archivo.php en la funcion buildForm o funcion principal del Controller (depende del caso), se le agrega el parámetro $rev_id = NULL
        */
        $project_link = Link::fromTextAndUrl('Revision #'.$rev, $url);
        $project_link = $project_link->toRenderable();
        $project_link['#attributes'] = array('class' => array('button'));
        $form[$rev] = array(
          '#markup' => render($project_link),
        );
      }
    } else {
      $form['empty'] = array('#markup' => 'No tienes revisiones pendientes.',);
    }
    return $form;
  }
}