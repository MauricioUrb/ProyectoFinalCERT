<?php

namespace Drupal\hello_world\Controller;

class HelloWorldController {
	public function hello(){
		return array(
				'#markup' => 'Contenido etc etc.'
		);
	}
}
?>