<?php 
/*
Plugin Name: 3D File Submission.
Description: MITM solution for 3D file submission.
Version: 1.0
Author: Kevin, Milos en Kasper.
*/

defined('ABSPATH') or die('died');

function register_custom_post_types() {
    register_post_type('3d_submission',
        array(
            'labels'      => array(
                'name'          => __('3D-inzendingen'),
                'singular_name' => __('3D-inzending'),
            ),
            'public'      => true,
            'show_ui'     => true,
            'has_archive' => false,
            'supports'    => array('title'),
            'capabilities' => array(
                'create_posts' => 'do_not_allow',
            ),
            'map_meta_cap' => true,
        )
    );

    register_post_type('printer',
        array(
            'labels' => array(
                'name' => __('Printers'),
                'singular_name' => __('Printer')
            ),
            'public' => true,
            'show_ui' => true,
            'supports' => array('title', 'custom-fields'),
            'menu_icon' => 'dashicons-printer',
        )
    );
}
add_action('init', 'register_custom_post_types');

function add_meta_boxes() {
    $post_type = '3d_submission';
    if (!post_type_exists($post_type)) return;

    add_meta_box(
        'approval_status',
        'Behandeling Status',
        'render_approval_meta_box',
        $post_type,
        'side',
        'high'
    );
    
    
    add_meta_box(
        'file_preview',
        '3D Bestand Preview',
        'render_file_preview_meta_box',
        '3d_submission',
        'normal',
        'high'
    );
    add_meta_box(
        'extra_info',
        'Extra Info',
        'render_extra_info_meta_box', 
        '3d_submission',
        'side',
        'default'
    );

    add_meta_box(
        'printer_settings',
        'Printer Configuration',
        'render_printer_settings_meta_box',
        'printer',
        'normal',
        'high'
    );
}
add_action('add_meta_boxes', 'add_meta_boxes');

function render_extra_info_meta_box($post) {
    $fields = [
        'klas_opleiding' => 'Klas/Opleiding',
        'Motief' => 'Motief',
        'toelichting' => 'Toelichting',
        'gcode_material' => 'Materiaal (uit G-code)',
        'gcode_color' => 'Kleur (uit G-code)'
    ];

    // Add download links for files
    $model_url = get_post_meta($post->ID, '3d_file_url', true);
    $gcode_url = get_post_meta($post->ID, 'gcode_file_url', true);

    if ($model_url) {
        echo '<p><strong>3D Bestand:</strong><br>';
        echo '<a href="' . esc_url($model_url) . '" download class="button button-small">Download 3D Bestand</a></p>';
    }

    if ($gcode_url) {
        echo '<p><strong>G-code Bestand:</strong><br>';
        echo '<a href="' . esc_url($gcode_url) . '" download class="button button-small">Download G-code</a></p>';
    }

    foreach ($fields as $meta_key => $label) {
        $value = get_post_meta($post->ID, $meta_key, true);
        if(!empty($value)) {
            echo '<p><strong>' . esc_html($label) . ':</strong><br>' . esc_html($value) . '</p>';
        }
    }

    // Get material information
    $material_names = get_post_meta($post->ID, 'material_names', true);
    $material_guids = get_post_meta($post->ID, 'material_guids', true);
    
    if (!empty($material_names)) {
        echo '<p><strong>Materiaal (Printer):</strong><br>';
        foreach ($material_names as $material_name) {
            echo esc_html($material_name) . '<br>';
        }
        echo '</p>';
    } elseif (!empty($material_guids)) {
        echo '<p><strong>Material GUIDs:</strong><br>';
        foreach ($material_guids as $guid) {
            echo esc_html($guid) . '<br>';
        }
        echo '</p>';
    }

    echo '<div class="gcode-info-section">';
    
    // Estimated print time
    $estimated_time = get_post_meta($post->ID, 'estimated_time', true);
    if ($estimated_time) {
        $hours = floor($estimated_time / 3600);
        $minutes = floor(($estimated_time % 3600) / 60);
        $time_str = ($hours > 0 ? $hours . ' uur ' : '') . $minutes . ' minuten';
        echo '<p><strong>Geschatte Printtijd:</strong><br>' . esc_html($time_str) . '</p>';
    }
    
    // Extract and display additional G-code parameters
    $gcode_url = get_post_meta($post->ID, 'gcode_file_url', true);
    if ($gcode_url) {
        $upload_dir = wp_upload_dir();
        $file_path = str_replace($upload_dir['baseurl'], $upload_dir['basedir'], $gcode_url);
        
        if (file_exists($file_path)) {
            // Read the first 200 lines of the G-code file to find parameter info
            $lines = file($file_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES, null);
            $lines = array_slice($lines, 0, 200); // Just read the header part
            
            $gcode_params = [
                'printcore' => ['pattern' => '/;Extruder (?:Brandname|Type): ([a-zA-Z]{2} [0-9][,.][0-9])/', 'label' => 'Printcore Type'],
                'printcore_alt' => ['pattern' => '/;PRINTCORE_ID:([a-zA-Z]{2} ?[0-9][,.][0-9])/', 'label' => 'Printcore Type'],
                'nozzle_name' => ['pattern' => '/;EXTRUDER_TRAIN\.0\.NOZZLE\.NAME:([a-zA-Z]{2} [0-9][,.][0-9])/', 'label' => 'Printcore Type'],
                'nozzle_size' => ['pattern' => '/;Nozzle diameter: ?([0-9][,.][0-9]+)/', 'label' => 'Nozzle Diameter'],
                'nozzle_size_alt' => ['pattern' => '/;EXTRUDER_TRAIN\.0\.NOZZLE\.DIAMETER:([0-9][,.][0-9]+)/', 'label' => 'Nozzle Diameter'],
                'layer_height' => ['pattern' => '/;Layer height: ([0-9.]+)/', 'label' => 'Layer Hoogte'],
                'layer_height_alt' => ['pattern' => '/;LAYER_HEIGHT:([0-9.]+)/', 'label' => 'Layer Hoogte'],
                'infill' => ['pattern' => '/;Infill: ([0-9.]+)%/', 'label' => 'Vulling Percentage'],
                'infill_alt' => ['pattern' => '/;INFILL_SPARSE_DENSITY:([0-9.]+)/', 'label' => 'Vulling Percentage'],
                'print_temp' => ['pattern' => '/;Print temperature: ([0-9.]+)/', 'label' => 'Print Temperatuur'],
                'print_temp_alt' => ['pattern' => '/;EXTRUDER_TRAIN\.0\.INITIAL_TEMPERATURE:([0-9.]+)/', 'label' => 'Print Temperatuur'],
                'bed_temp' => ['pattern' => '/;Bed temperature: ([0-9.]+)/', 'label' => 'Bed Temperatuur'],
                'bed_temp_alt' => ['pattern' => '/;BUILD_PLATE\.TEMPERATURE:([0-9.]+)/', 'label' => 'Bed Temperatuur'],
                'print_speed' => ['pattern' => '/;Print speed: ([0-9.]+)/', 'label' => 'Print Snelheid'],
                'retraction' => ['pattern' => '/;Retraction: ([0-9.]+)/', 'label' => 'Retractie'],
                'dimensions' => ['pattern' => '/;MINX:([0-9.-]+) MAXX:([0-9.-]+) MINY:([0-9.-]+) MAXY:([0-9.-]+) MINZ:([0-9.-]+) MAXZ:([0-9.-]+)/', 'label' => 'Dimensies'],
                'filament_used' => ['pattern' => '/;Filament used: ([0-9.]+)m/', 'label' => 'Filament Gebruikt'],
                'filament_used_alt' => ['pattern' => '/;FILAMENT_USED:([0-9.]+)/', 'label' => 'Filament Gebruikt'],
                'num_layers' => ['pattern' => '/;LAYER_COUNT:([0-9]+)/', 'label' => 'Aantal Lagen']
            ];
            
            $content = implode("\n", $lines);
            $processed_labels = array();
            
            // First try to find printcore information
            $printcore_found = false;
            foreach (['printcore', 'printcore_alt', 'nozzle_name'] as $key) {
                if (preg_match($gcode_params[$key]['pattern'], $content, $matches)) {
                    echo '<p><strong>' . esc_html($gcode_params[$key]['label']) . ':</strong><br>' . esc_html($matches[1]) . '</p>';
                    $printcore_found = true;
                    $processed_labels[] = $gcode_params[$key]['label'];
                    break;
                }
            }
            
            // If no printcore type found, try nozzle size
            if (!$printcore_found) {
                $nozzle_size = null;
                foreach (['nozzle_size', 'nozzle_size_alt'] as $key) {
                    if (preg_match($gcode_params[$key]['pattern'], $content, $matches)) {
                        $nozzle_size = str_replace(',', '.', $matches[1]);
                        echo '<p><strong>' . esc_html($gcode_params[$key]['label']) . ':</strong><br>' . esc_html($nozzle_size) . ' mm</p>';
                        $processed_labels[] = $gcode_params[$key]['label'];
                        
                        // Try to infer printcore type from nozzle diameter
                        if ($nozzle_size == '0.4') {
                            echo '<p><strong>Printcore Type (afgeleid):</strong><br>AA 0.4</p>';
                        } elseif ($nozzle_size == '0.8') {
                            echo '<p><strong>Printcore Type (afgeleid):</strong><br>BB 0.8</p>';
                        } elseif ($nozzle_size == '0.25') {
                            echo '<p><strong>Printcore Type (afgeleid):</strong><br>AA 0.25</p>';
                        }
                        break;
                    }
                }
            }
            
            // Check for manually entered printcore
            $printcore_meta = get_post_meta($post->ID, 'Printcore', true);
            if (!empty($printcore_meta) && !$printcore_found) {
                echo '<p><strong>Printcore:</strong><br>' . esc_html($printcore_meta) . '</p>';
            }
            
            // Process other G-code parameters
            foreach ($gcode_params as $key => $param) {
                // Skip printcore-related parameters and already processed labels
                if (in_array($key, ['printcore', 'printcore_alt', 'nozzle_name', 'nozzle_size', 'nozzle_size_alt']) || 
                    in_array($param['label'], $processed_labels)) {
                    continue;
                }
                
                // Skip alternate patterns for labels we've already processed
                if (strpos($key, '_alt') !== false) {
                    $base_key = str_replace('_alt', '', $key);
                    if (in_array($gcode_params[$base_key]['label'], $processed_labels)) {
                        continue;
                    }
                }
                
                if (preg_match($param['pattern'], $content, $matches)) {
                    if ($key === 'dimensions' && count($matches) >= 7) {
                        $width = number_format(abs($matches[2] - $matches[1]), 1);
                        $depth = number_format(abs($matches[4] - $matches[3]), 1);
                        $height = number_format(abs($matches[6] - $matches[5]), 1);
                        echo '<p><strong>' . esc_html($param['label']) . ':</strong><br>' . 
                             esc_html($width . ' x ' . $depth . ' x ' . $height . ' mm') . '</p>';
                    } else {
                        $unit = '';
                        switch ($key) {
                            case 'layer_height': case 'layer_height_alt': $unit = ' mm'; break;
                            case 'infill': case 'infill_alt': $unit = '%'; break;
                            case 'print_temp': case 'print_temp_alt': 
                            case 'bed_temp': case 'bed_temp_alt': $unit = '°C'; break;
                            case 'print_speed': $unit = ' mm/s'; break;
                            case 'filament_used': case 'filament_used_alt': $unit = ' m'; break;
                        }
                        echo '<p><strong>' . esc_html($param['label']) . ':</strong><br>' . 
                             esc_html($matches[1] . $unit) . '</p>';
                        
                        $processed_labels[] = $param['label'];
                    }
                }
            }
            
            // Look for slicer-specific info (Cura, Prusa Slicer, etc.)
            $slicer_info = '';
            if (preg_match('/;Generated with (Cura|PrusaSlicer|Slic3r|Simplify3D) ([0-9.]+)/', $content, $matches)) {
                $slicer_info = $matches[1] . ' ' . $matches[2];
                echo '<p><strong>Slicer Software:</strong><br>' . esc_html($slicer_info) . '</p>';
            } elseif (preg_match('/;GENERATOR.NAME:([^;]+)/', $content, $matches)) {
                echo '<p><strong>Slicer Software:</strong><br>' . esc_html(trim($matches[1])) . '</p>';
            }
        }
    }
    
    echo '</div>';

    $author_id = $post->post_author;
    $user = get_userdata($author_id);
    $submitter_name = $user ? $user->display_name : 'Onbekend';
    $submitter_email = get_post_meta($post->ID, 'submitter_email', true);

    if (empty($submitter_email)) {
        $submitter_email = $user ? $user->user_email : 'Onbekend';
    }

    echo '<p><strong>Inzender Naam:</strong><br>' . esc_html($submitter_name) . '</p>';
    echo '<p><strong>Inzender Email:</strong><br>' . esc_html($submitter_email) . '</p>';

    $printer_id = get_post_meta($post->ID, 'selected_printer', true);
    $printer_name = 'Geen printer geselecteerd';
    if ($printer_id) {
        $printer_post = get_post($printer_id);
        $printer_name = $printer_post ? $printer_post->post_title : '(verwijderde printer)';
    }
    echo '<p><strong>Geselecteerde Printer:</strong><br>' . esc_html($printer_name) . '</p>';
}

/**
 * Get readable material name from GUID
 * 
 * @param string $guid The material GUID
 * @return string|false The material name or false if not found
 */
function get_material_name_from_guid($guid) {
    // Material GUID mapping
    $guid_mapping = array(
        '44a029e6-e31b-4c9e-a12f-9282e29a92ff' => 'PLA (Ultimaker Silver Metallic)',
        '506c9f0d-e3aa-4bd4-b2d2-23e2425b1aa9' => 'PLA (Ultimaker White)',
        '98c05714-bf4e-4455-ba27-57d74fe331e4' => 'PLA (Ultimaker Black)',
        '1cbfb605-f626-4b36-9fea-9996bfaba577' => 'PLA (Ultimaker Red)',
        '88754136-ba51-4324-afbb-de42689918d4' => 'PLA (Ultimaker Blue)',
        '7e47a48a-d454-4fa6-a965-fc127f04c6c6' => 'PLA (Ultimaker Green)',
        'fa056fce-e0c8-4aa1-91ae-35fbda42d7a4' => 'ABS (Ultimaker Black)',
        '8b2b9b13-b681-4f8b-a465-456c8f0217d5' => 'ABS (Ultimaker White)',
        '44e19c85-500c-4401-af0a-3c356759286b' => 'ABS (Ultimaker Blue)',
        'b22b8cbe-278d-4245-a420-2d1cb783b16e' => 'ABS (Ultimaker Red)',
        '22a342cb-156a-4e8f-ab56-7365e4759a0c' => 'ABS (Ultimaker Green)',
        '5c78dc2c-e16f-4b9e-a6a6-9386c97312ed' => 'PETG (Ultimaker Black)',
        'da1872c4-ff93-42d0-8bd5-da7c8663cfcb' => 'PETG (Ultimaker White)',
        '6df69b13-2d31-4e29-9f23-a6b337ffe61a' => 'PETG (Ultimaker Blue)',
        'bfdb0787-032d-4cf5-9975-964132bd641c' => 'PLA (Ultimaker Yellow)',
    );
    
    // Check if the GUID exists in our mapping
    if (isset($guid_mapping[$guid])) {
        return $guid_mapping[$guid];
    }
    
    // If not found in static mapping, try to fetch from printer API
    $printers = get_posts([
        'post_type' => 'printer',
        'posts_per_page' => -1,
        'meta_key' => 'printer_ip',
        'meta_compare' => 'EXISTS'
    ]);
    
    foreach ($printers as $printer) {
        $printer_ip = get_post_meta($printer->ID, 'printer_ip', true);
        if (empty($printer_ip)) continue;
        
        $printer_api_url = "http://{$printer_ip}/cluster-api/v1/materials/";
        $response = wp_remote_get($printer_api_url, array(
            'timeout' => 10,
            'sslverify' => false
        ));

        if (!is_wp_error($response)) {
            $materials_data = json_decode(wp_remote_retrieve_body($response), true);
            
            if (is_array($materials_data)) {
                foreach ($materials_data as $material) {
                    if (isset($material['guid']) && $material['guid'] === $guid) {
                        $brand = isset($material['brand']) ? $material['brand'] : 'Unknown Brand';
                        $name = isset($material['name']) ? $material['name'] : 
                               (isset($material['material']) ? $material['material'] : 'Unknown Type');
                        $color = isset($material['color']) ? $material['color'] : 'Unknown Color';
                        
                        return "$brand $name ($color)";
                    }
                }
            }
        }
    }
    
    // If still not found, return generic message
    return 'Onbekend materiaal (GUID: ' . substr($guid, 0, 8) . '...)';
}

function render_approval_meta_box($post) {
    $status = get_post_meta($post->ID, 'approval_status', true) ?: 'inbehandeling';
    $rejection_reason = get_post_meta($post->ID, 'rejection_reason', true);
    $failure_reason = get_post_meta($post->ID, 'failure_reason', true);
    ?>
    <div class="approval-status-container">
        <label for="approval_status">Behandeling Status:</label>
        <select name="approval_status" id="approval_status" style="width:100%; margin-bottom:15px;">
            <option value="inbehandeling" <?php selected($status, 'inbehandeling'); ?>>In behandeling</option>
            <option value="goedgekeurd" <?php selected($status, 'goedgekeurd'); ?>>Goedgekeurd</option>
            <option value="afgewezen" <?php selected($status, 'afgewezen'); ?>>Afgewezen</option>
            <option value="geslaagd" <?php selected($status, 'geslaagd'); ?>>Geslaagd</option>
            <option value="gefaald" <?php selected($status, 'gefaald'); ?>>Gefaald</option>
        </select>

        <div id="rejection_reason_container" style="margin-top:10px;">
            <label for="rejection_reason">Reden voor afwijzing:</label>
            <textarea name="rejection_reason" id="rejection_reason" 
                      rows="4" style="width:100%;" 
                      placeholder="Geef een gedetailleerde reden voor afwijzing..."><?php 
                      echo esc_textarea($rejection_reason); ?></textarea>
        </div>

        <div id="failure_reason_container" style="margin-top:10px;">
            <label for="failure_reason">Reden voor mislukking:</label>
            <textarea name="failure_reason" id="failure_reason" 
                      rows="4" style="width:100%;" 
                      placeholder="Beschrijf wat er misging tijdens het printen..."><?php 
                      echo esc_textarea($failure_reason); ?></textarea>
        </div>

        <?php wp_nonce_field('approval_meta_box', 'approval_meta_box_nonce'); ?>

        <div style="margin-top: 20px; padding-top: 15px; border-top: 1px solid #ddd;">
            <?php submit_button(
                __('Update Status'), 
                'primary large', 
                'save', 
                false, 
                array('style' => 'width: 100%;')
            ); ?>
        </div>
    </div>
    <?php
}


function render_file_preview_meta_box($post) {
    $model_url = get_post_meta($post->ID, '3d_file_url', true);
    $gcode_url = get_post_meta($post->ID, 'gcode_file_url', true);
    
    echo '<div class="file-previews-container">';

    if ($model_url) {
        echo '<div class="preview-section">';
        echo '<h3>3D Model Preview</h3>';
        
        $model_extension = strtolower(pathinfo($model_url, PATHINFO_EXTENSION));
        
        if (in_array($model_extension, ['glb', 'gltf'])) {
            echo '<model-viewer 
                    src="' . esc_url($model_url) . '" 
                    alt="3D Model Preview" 
                    auto-rotate 
                    camera-controls 
                    style="width: 100%; height: 400px; margin-bottom: 20px;">
                  </model-viewer>';
        }
        else {
            echo '<div id="threejs-model-viewer" style="width: 100%; height: 400px; margin-bottom: 20px;"></div>';
            echo '<script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js"></script>';
            echo '<script src="https://cdn.jsdelivr.net/npm/three@0.128.0/examples/js/controls/OrbitControls.js"></script>';
            switch($model_extension) {
                case 'stl':
                    echo '<script src="https://cdn.jsdelivr.net/npm/three@0.128.0/examples/js/loaders/STLLoader.js"></script>';
                    break;
                case 'obj':
                    echo '<script src="https://cdn.jsdelivr.net/npm/three@0.128.0/examples/js/loaders/OBJLoader.js"></script>';
                    break;
                case 'fbx':
                    echo '<script src="https://cdn.jsdelivr.net/npm/three@0.128.0/examples/js/loaders/FBXLoader.js"></script>';
                    break;
                case '3ds':
                    echo '<script src="https://cdn.jsdelivr.net/npm/three@0.128.0/examples/js/loaders/TDSLoader.js"></script>';
                    break;
            }

            ?>
            <script>
            (function() {
                const container = document.getElementById('threejs-model-viewer');
                const scene = new THREE.Scene();
                const camera = new THREE.PerspectiveCamera(75, container.clientWidth / container.clientHeight, 0.1, 1000);
                const renderer = new THREE.WebGLRenderer({ antialias: true });
                renderer.setSize(container.clientWidth, container.clientHeight);
                container.appendChild(renderer.domElement);

                scene.add(new THREE.AmbientLight(0xffffff, 0.5));
                const directionalLight = new THREE.DirectionalLight(0xffffff, 1);
                directionalLight.position.set(1, 1, 1);
                scene.add(directionalLight);

                const controls = new THREE.OrbitControls(camera, renderer.domElement);
                controls.enableDamping = true;
                controls.dampingFactor = 0.05;

                const loader = new THREE.FileLoader();
                const fileUrl = '<?php echo esc_url($model_url); ?>';
                
                <?php if ($model_extension === 'stl') : ?>
                    new THREE.STLLoader().load(fileUrl, geometry => {
                        const material = new THREE.MeshPhongMaterial({ color: 0x555555 });
                        const mesh = new THREE.Mesh(geometry, material);
                        mesh.rotation.x = -Math.PI / 2;
                        scene.add(mesh);
                        centerAndScale(mesh);
                    });
                <?php elseif ($model_extension === 'obj') : ?>
                    new THREE.OBJLoader().load(fileUrl, object => {
                        object.rotation.x = -Math.PI / 2;
                        scene.add(object);
                        centerAndScale(object);
                    });
                <?php endif; ?>

                function centerAndScale(object) {
                    const bbox = new THREE.Box3().setFromObject(object);
                    const center = bbox.getCenter(new THREE.Vector3());
                    const size = bbox.getSize(new THREE.Vector3());

                    object.position.sub(center);
                    camera.position.copy(center);
                    camera.position.z = size.length() * 1.5;
                    camera.lookAt(center);
                    controls.update();
                }

                const animate = () => {
                    requestAnimationFrame(animate);
                    controls.update();
                    renderer.render(scene, camera);
                };
                animate();

                window.addEventListener('resize', () => {
                    camera.aspect = container.clientWidth / container.clientHeight;
                    camera.updateProjectionMatrix();
                    renderer.setSize(container.clientWidth, container.clientHeight);
                });
            })();
            </script>
            <?php
        }
        echo '</div>';
    } else {
        echo '<p>No 3D file attached</p>';
    }

        if ($gcode_url) {
            echo '<div class="preview-section">';
            echo '<h3>G-code Preview</h3>';
            echo '<div id="gcode-viewer" style="width: 100%; height: 600px; border: 1px solid #ccc; position: relative;">';
            echo '<div id="loading-overlay" style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; background: rgba(255,255,255,0.8); display: flex; justify-content: center; align-items: center;">Loading G-code preview...</div>';
            echo '</div>';
            ?>
            <script>
            (function() {
                const container = document.getElementById('gcode-viewer');
                const loadingOverlay = document.getElementById('loading-overlay');

                const scene = new THREE.Scene();
                const camera = new THREE.PerspectiveCamera(75, container.clientWidth / container.clientHeight, 0.1, 1000);
                const renderer = new THREE.WebGLRenderer({ antialias: true });
                renderer.setSize(container.clientWidth, container.clientHeight);
                container.appendChild(renderer.domElement);

                scene.add(new THREE.AmbientLight(0xffffff, 0.8));
                const directionalLight = new THREE.DirectionalLight(0xffffff, 0.5);
                directionalLight.position.set(1, 1, 1);
                scene.add(directionalLight);

                scene.add(new THREE.AxesHelper(100));

                const controls = new THREE.OrbitControls(camera, renderer.domElement);
                controls.enableDamping = true;
                controls.dampingFactor = 0.05;
                controls.rotateSpeed = 0.5;

                fetch('<?php echo esc_url($gcode_url); ?>')
                    .then(response => response.text())
                    .then(gcode => {
                        const lines = gcode.split('\n');
                        const positions = [];
                        const colors = [];
                        let currentPos = new THREE.Vector3(0, 0, 0);
                        let currentE = 0;
                        let min = new THREE.Vector3(Infinity, Infinity, Infinity);
                        let max = new THREE.Vector3(-Infinity, -Infinity, -Infinity);
    
                        lines.forEach(line => {
                            line = line.trim();
                            if (!line.startsWith('G0') && !line.startsWith('G1')) return;
    
                            const isTravel = line.startsWith('G0');
                            const newPos = currentPos.clone();
                            let hasExtrusion = false;
    
                            line.split(' ').forEach(token => {
                                if (token.startsWith('X')) newPos.x = parseFloat(token.substring(1));
                                if (token.startsWith('Y')) newPos.y = parseFloat(token.substring(1));
                                if (token.startsWith('Z')) newPos.z = parseFloat(token.substring(1));
                                if (token.startsWith('E')) {
                                    const eValue = parseFloat(token.substring(1));
                                    hasExtrusion = eValue > currentE;
                                    currentE = eValue;
                                }
                            });

                            min.min(newPos);
                            max.max(newPos);

                            let color = new THREE.Color();
                            if (isTravel) {
                                color.setHex(0xFF0000);
                            } else {
                                color = hasExtrusion ? 
                                    new THREE.Color(0x00FF00) :
                                    new THREE.Color(0x0000FF);
                            }

                            positions.push(currentPos.x, currentPos.y, currentPos.z);
                            colors.push(color.r, color.g, color.b);
                            positions.push(newPos.x, newPos.y, newPos.z);
                            colors.push(color.r, color.g, color.b);
    
                            currentPos.copy(newPos);
                        });

                        const geometry = new THREE.BufferGeometry();
                        geometry.setAttribute('position', 
                            new THREE.Float32BufferAttribute(positions, 3));
                        geometry.setAttribute('color', 
                            new THREE.Float32BufferAttribute(colors, 3));
    
                        const material = new THREE.LineBasicMaterial({
                            vertexColors: true,
                            linewidth: 1
                        });
    
                        const toolpath = new THREE.LineSegments(geometry, material);
                        toolpath.rotation.x = -Math.PI / 2;
                        scene.add(toolpath);

                        const center = new THREE.Vector3();
                        center.addVectors(min, max).multiplyScalar(0.5);
                        
                        const size = new THREE.Vector3();
                        size.subVectors(max, min);
                        
                        camera.position.copy(center);
                        camera.position.z += size.length() * 1.5;
                        camera.lookAt(center);
                        
                        controls.target.copy(center);
                        controls.update();

                        loadingOverlay.style.display = 'none';
                    })
                    .catch(error => {
                        console.error('Error loading G-code:', error);
                        loadingOverlay.textContent = 'Error loading G-code preview';
                    });

                const animate = () => {
                    requestAnimationFrame(animate);
                    controls.update();
                    renderer.render(scene, camera);
                };
                animate();

                window.addEventListener('resize', () => {
                    camera.aspect = container.clientWidth / container.clientHeight;
                    camera.updateProjectionMatrix();
                    renderer.setSize(container.clientWidth, container.clientHeight);
                });
            })();
            </script>
            <?php
            echo '</div>';
        } else {
            echo '<p>No G-code file attached</p>';
        }
    
        echo '</div>';
    }

function custom_upload_mimes($mimes) {
    $mimes['gcode'] = 'application/x-gcode'; 
    $mimes['stl'] = 'application/sla';
    $mimes['obj'] = 'text/plain';
    $mimes['fbx'] = 'application/octet-stream';
    
    return $mimes;
}
add_filter('upload_mimes', 'custom_upload_mimes');

function debug_upload_mimes($params) {
    error_log(print_r($params, true));
    return $params;
}
add_filter('wp_check_filetype_and_ext', 'debug_upload_mimes', 10, 4);

function save_submission_data($post_id) {
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (!current_user_can('edit_post', $post_id)) return;
    if (get_post_type($post_id) !== '3d_submission') return;

    if (!isset($_POST['approval_status'])) {
        return;
    }

    $new_status = sanitize_text_field($_POST['approval_status']);
    $old_status = get_post_meta($post_id, 'approval_status', true);

    if ($new_status === 'afgewezen') {
        $rejection_reason = isset($_POST['rejection_reason']) ? trim($_POST['rejection_reason']) : '';
        if (empty($rejection_reason)) {
            add_filter('redirect_post_location', function($location) {
                return add_query_arg('3d_error', 'rejection_reason_required', $location);
            });
            update_post_meta($post_id, 'approval_status', $old_status);
            return;
        } else {
            update_post_meta($post_id, 'approval_status', $new_status);
            update_post_meta($post_id, 'rejection_reason', sanitize_textarea_field($rejection_reason));
        }
    } elseif ($new_status === 'gefaald') {
        $failure_reason = isset($_POST['failure_reason']) ? trim($_POST['failure_reason']) : '';
        if (empty($failure_reason)) {
            add_filter('redirect_post_location', function($location) {
                return add_query_arg('3d_error', 'failure_reason_required', $location);
            });
            update_post_meta($post_id, 'approval_status', $old_status);
            return;
        } else {
            update_post_meta($post_id, 'approval_status', $new_status);
            update_post_meta($post_id, 'failure_reason', sanitize_textarea_field($failure_reason));
        }
    } elseif ($new_status === 'geslaagd') {
        update_post_meta($post_id, 'approval_status', $new_status);
        delete_post_meta($post_id, 'failure_reason');
    } else {
        update_post_meta($post_id, 'approval_status', $new_status);
        delete_post_meta($post_id, 'rejection_reason');
    }

    if ($new_status !== $old_status) {
        send_approval_notification($post_id, $old_status, $new_status);
    }
    if ($new_status === 'goedgekeurd') {
        send_gcode_to_printer($post_id);
    }
}

add_action('save_post', 'save_submission_data');
function admin_notice_rejection_reason() {
    if (!isset($_GET['3d_error'])) return;

    $error_type = sanitize_text_field($_GET['3d_error']);
    $messages = [
        'rejection_reason_required' => [
            'type' => 'error',
            'title' => 'Afwijzing onvolledig',
            'content' => 'Bij het afwijzen van een inzending is een afwijzingsreden vereist.'
        ],
        'failure_reason_required' => [
            'type' => 'error',
            'title' => 'Mislukking registratie onvolledig',
            'content' => 'Bij het markeren als gefaald is een reden voor de mislukking vereist.'
        ],
        'invalid_status_change' => [
            'type' => 'warning',
            'title' => 'Ongeldige statuswijziging',
            'content' => 'De geselecteerde statusovergang is niet toegestaan.'
        ]
    ];

    if (array_key_exists($error_type, $messages)) {
        $msg = $messages[$error_type];
        ?>
        <div class="notice notice-<?php echo esc_attr($msg['type']); ?> is-dismissible">
            <div class="notice-content">
                <h3 style="margin: 0.5em 0;"><?php echo esc_html($msg['title']); ?></h3>
                <p><?php echo esc_html($msg['content']); ?></p>
                <?php if ($error_type === 'rejection_reason_required') : ?>
                    <ul style="margin: 0.5em 0; list-style: disc inside;">
                        <li>Geef een duidelijke technische reden op</li>
                        <li>Verwijs eventueel naar specifieke richtlijnen</li>
                        <li>Suggesties voor verbetering zijn optioneel</li>
                    </ul>
                <?php elseif ($error_type === 'failure_reason_required') : ?>
                    <ul style="margin: 0.5em 0; list-style: disc inside;">
                        <li>Beschrijf wat er mis ging tijdens het printen</li>
                        <li>Vermeld eventuele printerfoutcodes</li>
                        <li>Geef aan of herbewerking nodig is</li>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }
}
add_action('admin_notices', 'admin_notice_rejection_reason');

function submission_form_shortcode() {
    ob_start();
    if (!is_user_logged_in()) {
        echo '<p>U moet ingelogd zijn om een bestand in te dienen. <a href="' . esc_url(wp_login_url(get_permalink())) . '">Klik hier om in te loggen</a> of <a href="' . esc_url(wp_registration_url()) . '">registreer hier</a>.</p>';
        return ob_get_clean();
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_3d_file_btn'])) {
        handle_file_submission();
    }

    display_submission_form();
    
    return ob_get_clean();
}
add_shortcode('3d_file_submission', 'submission_form_shortcode');

function handle_file_submission() {
    require_once(ABSPATH . 'wp-admin/includes/file.php');
    require_once(ABSPATH . 'wp-admin/includes/image.php');

    echo '<div id="submission-status" class="submission-notice">Processing submission...</div>';

    if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'submit_3d_file')) {
        echo '<div class="error">Security check failed. Please try again.</div>';
        return;
    }

    $user_id = get_current_user_id();
    if (!$user_id) {
        echo '<div class="error">You must be logged in to submit files.</div>';
        return;
    }

    if (empty($_POST['file_title'])) {
        echo '<div class="error">Please provide a file title.</div>';
        return;
    }

    if (empty($_FILES['3d_file'])) {
        echo '<div class="error">3D file upload failed. Please try again.</div>';
        return;
    }

    $upload_overrides = ['test_form' => false];
    $uploaded_3d_file = $_FILES['3d_file'];
    $movefile_3d = wp_handle_upload($uploaded_3d_file, $upload_overrides);

    if (isset($movefile_3d['error'])) {
        echo '<div class="error">3D file error: ' . esc_html($movefile_3d['error']) . '</div>';
        return;
    }

    $post_id = wp_insert_post([
        'post_title'   => sanitize_text_field($_POST['file_title']),
        'post_type'    => '3d_submission',
        'post_status'  => 'publish',
        'post_author'  => $user_id
    ]);

    if (is_wp_error($post_id)) {
        echo '<div class="error">Submission error: ' . $post_id->get_error_message() . '</div>';
        return;
    }

    $estimated_time = 0;
    if (!empty($_FILES['gcode_file']['name'])) {
        $uploaded_gcode = $_FILES['gcode_file'];
        
        if ($uploaded_gcode['error'] === UPLOAD_ERR_OK) {
            $gcode_file_type = wp_check_filetype($uploaded_gcode['name']);
            
            if ($gcode_file_type['ext'] === 'gcode') {
                $gcode_movefile = wp_handle_upload($uploaded_gcode, $upload_overrides);
                
                if (!isset($gcode_movefile['error'])) {
                    update_post_meta($post_id, 'gcode_file_url', $gcode_movefile['url']);
                    
                    try {
                        $gcode_content = file_get_contents($gcode_movefile['file']);
                        
                        // Find all extruder material GUIDs
                        $material_guids = [];
                        if (preg_match_all('/;EXTRUDER_TRAIN\.\d+\.MATERIAL\.GUID:([a-fA-F0-9-]+)/', $gcode_content, $matches)) {
                            $material_guids = array_unique($matches[1]);
                        }
                        
                        if (!empty($material_guids)) {
                            update_post_meta($post_id, 'material_guids', $material_guids);
                            
                            // Get and store the material names
                            $material_names = [];
                            foreach ($material_guids as $guid) {
                                $material_name = get_material_name_from_guid($guid);
                                if ($material_name) {
                                    $material_names[] = $material_name;
                                }
                            }
                            if (!empty($material_names)) {
                                update_post_meta($post_id, 'material_names', $material_names);
                            }
                        }
                        
                        if (preg_match('/;TIME:(\d+)/', $gcode_content, $matches)) {
                            $estimated_time = (int)$matches[1];
                            update_post_meta($post_id, 'estimated_time', $estimated_time);
                        }
                    } catch (Exception $e) {
                        error_log('G-code parsing failed: ' . $e->getMessage());
                    }
                }
            }
        }
    }

    update_post_meta($post_id, '3d_file_url', $movefile_3d['url']);
    update_post_meta($post_id, 'approval_status', 'inbehandeling');
    update_post_meta($post_id, 'submitter_email', get_userdata($user_id)->user_email);

    $selected_printer = isset($_POST['selected_printer']) ? absint($_POST['selected_printer']) : 0;
    if ($selected_printer) {
        update_post_meta($post_id, 'selected_printer', $selected_printer);
        
        $printer_queue = get_post_meta($selected_printer, 'print_queue', true) ?: [];
        $printer_queue[] = [
            'post_id' => $post_id,
            'added' => current_time('mysql')
        ];
        update_post_meta($selected_printer, 'print_queue', $printer_queue);
    }

    $meta_fields = [
        'klas_opleiding',
        'materiaal',
        'Printcore',
        'Motief',
        'toelichting',
        'submission_deadline'
    ];
    
    foreach ($meta_fields as $field) {
        if (isset($_POST[$field])) {
            update_post_meta($post_id, $field, sanitize_text_field($_POST[$field]));
        }
    }

    send_approval_notification($post_id, '', 'inbehandeling');
    
    echo '<div class="success">Form submitted successfully! Your print is being reviewed.</div>';
    echo '<script>document.getElementById("submission-status").remove();</script>';
}

function add_3d_submission_styles() {
    ?>
    <style>
        #submission-messages .error {
            color: red;
            font-weight: bold;
            padding: 10px;
            margin: 10px 0;
            background-color: #ffeeee;
            border: 1px solid #ffcccc;
        }
        #submission-messages .success {
            color: green;
            font-weight: bold;
            padding: 10px;
            margin: 10px 0;
            background-color: #eeffee;
            border: 1px solid #ccffcc;
        }
        .printer-status-overview {
            margin: 20px 0;
            padding: 15px;
            background: #f8f9fa;
            border: 1px solid #eee;
        }
        
        .printer-status {
            padding: 10px;
            margin: 5px 0;
            border-radius: 4px;
        }
        
        .status-indicator {
            padding: 3px 8px;
            border-radius: 3px;
        }
        
        .status-indicator.available {
            background: #dff0d8;
            color: #3c763d;
        }
        
        .status-indicator.printing {
            background: #fcf8e3;
            color: #8a6d3b;
        }
    </style>
    <?php
}
add_action('wp_head', 'add_3d_submission_styles');

function display_submission_form() {
    $printers = get_posts([
        'post_type' => 'printer',
        'posts_per_page' => -1,
        'meta_key' => 'printer_ip',
        'meta_compare' => 'EXISTS'
    ]);
    
    echo '<div class="printer-status-overview">';
    echo '<h3>Printer Beschikbaarheid</h3>';
    
    foreach ($printers as $printer) {
        $printer_ip = get_post_meta($printer->ID, 'printer_ip', true);
        $status_data = get_print_job_data($printer_ip);
        
        echo '<div class="printer-status">';
        echo '<strong>' . esc_html($printer->post_title) . '</strong>: ';
        echo '<span class="status-indicator ' . esc_attr($status_data['state']) . '">';
        echo $status_data['state'] === 'offline' ? 'Beschikbaar' : 'Bezig met printen';
        echo '</span>';
        
        if ($status_data['state'] === 'printing') {
            $time_total = isset($status_data['time_total']) ? $status_data['time_total'] : 0;
            $time_elapsed = isset($status_data['time_elapsed']) ? $status_data['time_elapsed'] : 0;
            $remaining = max(0, $time_total - $time_elapsed);
            echo ' - Voltooiing over: ' . human_readable_time($remaining);
        }
        
        echo '</div>';
    }
    
    echo '</div>';
    ?>
    <form method="post" enctype="multipart/form-data">
        <?php wp_nonce_field('submit_3d_file'); ?>
        <p><label>Bestand Titel: <input type="text" name="file_title" required></label></p>
        <p><label>3D Bestand (STL, OBJ, etc.): <input type="file" name="3d_file" accept=".stl,.obj,.fbx,.3ds,.glb,.gltf" required></label></p>
        <p><label>G-code Bestand: <input type="file" name="gcode_file" accept=".gcode"></label></p>
        <p>
            <label for="Motief" required>Motief: </label>
            <select name="Motief">
                <option value="Prive">Prive</option>
                <option value="School">School</option>
            </select>
            <label>toelichting <input type="text" name="toelichting" required></label>
        </p>
        <p><label>Deadline: <input type="date" name="submission_deadline" required></label></p>
        
        <p>
            <label for="selected_printer">Selecteer Printer: </label>
            <select name="selected_printer" id="selected_printer" required>
                <option value="">Kies een printer...</option>
                <?php foreach ($printers as $printer) : ?>
                    <option value="<?php echo esc_attr($printer->ID); ?>">
                        <?php echo esc_html($printer->post_title); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </p>

        <p>
            <input type="submit" name="submit_3d_file_btn" value="Verzenden">
            <label>
                <input type="checkbox" name="rules_agree" required>
                Ik ga akkoord met de <a href="<?php echo get_permalink(get_page_by_title('3D Print Richtlijnen')); ?>" target="_blank">regels voor afdrukken</a>
            </label>
        </p>
    </form>

    <script>
            try {
                const response = await fetch(`/wp-json/3dprint/v1/printer-status/${printerId}`);
                const data = await response.json();
                
                const remaining = data.remaining || 0;
                const total = remaining + estimatedSeconds;
                document.getElementById('total-wait-time').textContent = formatTime(total);
            } catch (error) {
                console.error('Error fetching printer status:', error);
                document.getElementById('total-wait-time').textContent = 'Onbekend';
            }
        }

        function formatTime(seconds) {
            if (!seconds) return '0m';
            const hours = Math.floor(seconds / 3600);
            const minutes = Math.ceil((seconds % 3600) / 60);
            return [hours > 0 ? `${hours}u` : '', minutes > 0 ? `${minutes}m` : ''].join(' ').trim();
        }

        // Event listeners
        gcodeInput.addEventListener('change', updateTimeEstimates);
        printerSelect.addEventListener('change', () => {
            if (document.getElementById('estimated-time').textContent !== '-') {
                updateTotalWaitTime(parseInt(document.getElementById('estimated-time').dataset.seconds));
            }
        });
    })();
    </script>
    <?php
}

function get_printer_status_api(WP_REST_Request $request) {
    $printer_id = $request->get_param('id');
    $printer_ip = get_post_meta($printer_id, 'printer_ip', true);
    $status = get_print_job_data($printer_ip);
    
    return [
        'state' => $status['state']
    ];
}

function manage_3d_submission_columns($columns) {
    $new_columns = [
        'cb' => $columns['cb'],
        'title' => 'Titel',
        'author' => 'Auteur',
        'status' => 'Status',
        '3d_file' => '3D Bestand',
        'gcode_file' => 'G-code',
        'deadline' => 'Deadline',
        'date' => 'Datum'
    ];
    return $new_columns;
}

add_filter('manage_3d_submission_posts_columns', 'manage_3d_submission_columns');

function populate_3d_submission_columns($column, $post_id) {
    switch ($column) {
        case 'status':
            $status = get_post_meta($post_id, 'approval_status', true);
            switch ($status) {
                case 'geslaagd':
                    echo '<span style="color: green;">Geslaagd</span>';
                    break;
                case 'gefaald':
                    echo '<span style="color: red;">Gefaald</span>';
                    break;
                case 'inbehandeling':
                    echo '<span style="color: orange;">In behandeling</span>';
                    break;
                case 'goedgekeurd':
                    echo '<span style="color: green;">Goedgekeurd</span>';
                    break;
                case 'afgewezen':
                    echo '<span style="color: red;">Afgewezen</span>';
                    break;
                default:
                    echo 'Geen status';}
            break;
        case '3d_file':
            $file_url = get_post_meta($post_id, '3d_file_url', true);
            if ($file_url) {
                $file_name = basename($file_url);
                echo '<a href="' . esc_url($file_url) . '" target="_blank" download>'
                     . esc_html($file_name) . '</a>';
            } else {
                echo '<em>Geen 3D bestand</em>';
            }
            break;
        case 'gcode_file':
            $gcode_url = get_post_meta($post_id, 'gcode_file_url', true);
            if ($gcode_url) {
                $file_name = basename($gcode_url);
                echo '<a href="' . esc_url($gcode_url) . '" target="_blank" download>'
                     . esc_html($file_name) . '</a>';
            } else {
                echo '<em>Geen G-code</em>';
            }
            break;
        case 'deadline':
            $deadline = get_post_meta($post_id, 'submission_deadline', true);
            if ($deadline) {
                $formatted_date = date_i18n('j F Y', strtotime($deadline));
                $status_class = (strtotime($deadline) < time()) ? 'style="color:red;"' : '';
                echo '<span ' . $status_class . '>' . esc_html($formatted_date) . '</span>';
            } else {
                echo 'Geen deadline';
            }
            break;
        case 'author':
            $author_id = get_post_field('post_author', $post_id);
            $user = get_userdata($author_id);
            if ($user) {
                echo esc_html($user->display_name) . '<br>';
                echo '<small>' . esc_html($user->user_email) . '</small>';
            }
            break;
    }
}
add_action('manage_3d_submission_posts_custom_column', 'populate_3d_submission_columns', 10, 2);

function send_approval_notification($post_id, $old_status, $new_status) {
    $post = get_post($post_id);
    $submitter_email = get_post_meta($post_id, 'submitter_email', true);

    if(empty($submitter_email)) {
        $user = get_user_by('ID', $post->post_author);
        $submitter_email = $user->user_email;
    }

    $headers = array(
        'Content-Type: text/html; charset=UTF-8',
        'From: 3D Print Service <no-reply@' . $_SERVER['HTTP_HOST'] . '>'
    );

    $subject = "3D Print Status: " . $post->post_title;

    $message = "<p>Beste gebruiker,</p>";
    $message .= "<p>Er is een update voor uw 3D-printaanvraag <strong>" . esc_html($post->post_title) . "</strong>:</p>";

    switch($new_status) {
        case 'geslaagd':
            $subject = "✅ Print Succesvol: " . $post->post_title;
            $message .= "<p style='color: #2ecc71; font-weight: bold;'>Uw 3D-print is succesvol voltooid!</p>";
            $message .= "<p>U kunt uw print ophalen volgens de afspraken met de beheerder.</p>";
            break;

        case 'gefaald':
            $subject = "❌ Print Mislukt: " . $post->post_title;
            $message .= "<p style='color: #e74c3c; font-weight: bold;'>Helaas is uw 3D-print mislukt.</p>";
            
            $failure_reason = get_post_meta($post_id, 'failure_reason', true);
            if(!empty($failure_reason)) {
                $message .= "<div style='background: #f9ebea; padding: 15px; margin: 15px 0;'>";
                $message .= "<h4 style='margin-top:0;'>Reden van mislukking:</h4>";
                $message .= wpautop(esc_html($failure_reason));
                $message .= "</div>";
            }
            
            $message .= "<p>Neem contact op voor meer informatie over mogelijke oplossingen.</p>";
            break;

        case 'goedgekeurd':
            $subject = "✔️ Print Goedgekeurd: " . $post->post_title;
            $message .= "<p style='color: #27ae60;'>Uw print is goedgekeurd en wordt aan de wachtrij toegevoegd.</p>";
            $message .= "<p>U ontvangt een nieuwe update wanneer de print voltooid is.</p>";
            break;

        case 'afgewezen':
            $subject = "⛔ Print Afgewezen: " . $post->post_title;
            $message .= "<p style='color: #e67e22;'>Uw printaanvraag is helaas afgewezen.</p>";
            
            $rejection_reason = get_post_meta($post_id, 'rejection_reason', true);
            if(!empty($rejection_reason)) {
                $message .= "<div style='background: #fdefe6; padding: 15px; margin: 15px 0;'>";
                $message .= "<h4 style='margin-top:0;'>Reden van afwijzing:</h4>";
                $message .= wpautop(esc_html($rejection_reason));
                $message .= "</div>";
            }
            break;

        default:
            $message .= "<p>Status update: " . ucfirst($new_status) . "</p>";
    }

    $message .= "<hr style='margin:20px 0; border-color:#eee;'>";
    $message .= "<p style='font-size:0.9em; color:#666;'>";
    $message .= "Dit is een automatisch bericht. U kunt niet direct reageren op deze email.<br>";
    $message .= "3D Print Service - " . get_bloginfo('name') . "</p>";

    $attachments = array();
    if(in_array($new_status, ['afgewezen', 'gefaald'])) {
        $file_url = get_post_meta($post_id, '3d_file_url', true);
        if($file_url) {
            $upload_dir = wp_upload_dir();
            $file_path = str_replace($upload_dir['baseurl'], $upload_dir['basedir'], $file_url);
            if(file_exists($file_path)) {
                $attachments[] = $file_path;
            }
        }
    }

    $email_sent = wp_mail(
        $submitter_email,
        $subject,
        nl2br($message),
        $headers,
        $attachments
    );

    error_log("[3D Print] Status email voor {$post_id} ({$new_status}) " . ($email_sent ? "verzonden" : "mislukt"));
}

function plugin_activation_setup() {
    global $wpdb;

    register_custom_post_types();
    activate_daily_notifications();

    $default_printers = [
        ['title' => 'printer rechts', 'ip' => '172.22.19.238'],
        ['title' => 'printer links', 'ip' => '172.22.16.53']
    ];

    foreach ($default_printers as $printer) {
        if (!post_exists($printer['title'], '', '', 'printer')) {
            $printer_id = wp_insert_post([
                'post_title' => $printer['title'],
                'post_type' => 'printer',
                'post_status' => 'publish',
                'post_content' => ''
            ]);

            if ($printer_id && !is_wp_error($printer_id)) {
                update_post_meta($printer_id, 'printer_ip', $printer['ip']);
            }
        }
    }

    create_pages();

    $table_name = $wpdb->prefix . '3d_print_requests';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        request_title varchar(255) NOT NULL,
        request_description text NOT NULL,
        submission_date datetime NOT NULL,
        PRIMARY KEY  (id)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);

    update_option('permalink_structure', '/%postname%/');
    flush_rewrite_rules();

    if (!empty($wpdb->last_error)) {
        error_log('Database error: ' . $wpdb->last_error);
    }
}

function create_pages() {
    try {
        $submission_page = [
            'title' => '3D Bestand Indieningsformulier',
            'content' => "[3d_file_submission]\n[hotend_id_info]\n[current_material]",
            'option' => '3d_submission_page_id'
        ];

        $rules_page = [
            'title' => '3D Print Richtlijnen',
            'content' => get_rules_content(),
            'option' => '3d_print_rules_page_id'
        ];

        foreach ([$submission_page, $rules_page] as $page) {
            $existing = get_page_by_title($page['title']);
            
            if ($existing) {
                wp_update_post([
                    'ID' => $existing->ID,
                    'post_content' => $page['content']
                ]);
            } else {
                $page_id = wp_insert_post([
                    'post_title' => $page['title'],
                    'post_content' => $page['content'],
                    'post_status' => 'publish',
                    'post_type' => 'page',
                    'comment_status' => 'closed',
                    'ping_status' => 'closed'
                ]);
                
                if ($page_id && !is_wp_error($page_id)) {
                    update_option($page['option'], $page_id);
                }
            }
        }
    } catch (Exception $e) {
        error_log('Page error: ' . $e->getMessage());
    }
}

function get_rules_content() {
    return <<<HTML
<div class="print-rules-container">
    <h1>3D Print Richtlijnen</h1>
    <h2>Basics voor 3D printen</h2>
    <p>Beste student, voordat je gaat 3D-printen, zijn er een aantal dingen die je moet weten voordat je een printopdracht instuurt. Wij raden je aan om dit hele document te lezen om problemen te voorkomen.</p>

    <p><strong>Bestand slicen</strong><br>
    Om je bestand te slicen tot een .gcode raden wij je aan om de Cura-slicer te downloaden. Dit is de officiële slicer van de Ultimaker-printers die wij gebruiken. Andere slicers kunnen ook werken als je deze goed hebt ingesteld. Zorg ervoor dat je de Ultimaker S7 selecteert als printer.</p>

    <p><strong>Model positioneren</strong><br>
    Zorg ervoor dat je weet wat je print. Een 3D-geprint object is sterk, maar de zwakke punten zitten vaak tussen de lagen. Bijvoorbeeld: als je een haakje wilt printen waar je je jas aan kunt hangen, positioneer het model zo dat de printlijnen verticaal zijn. Zo zijn er geen zwakke punten en heb je een sterkere print.  
    Probeer ook het model zo plat mogelijk te positioneren, zodat er geen onnodige supports geprint hoeven te worden.</p>

    <p><strong>Materialen</strong><br>
    Op Scalda gebruiken wij drie soorten filament (plasticsoorten): PLA, ABS en PETG.</p>

    <ul>
        <li><strong>PLA-filament:</strong> Ideaal voor kunst- en ambachtelijke projecten, decoratieve objecten, prototyping en projecten waarbij milieuvriendelijkheid een prioriteit is.</li>
        <li><strong>ABS-filament:</strong> Geschikt voor functionele onderdelen, engineeringtoepassingen, onderdelen die bestendig moeten zijn tegen hogere temperaturen en toepassingen waarbij duurzaamheid van cruciaal belang is.</li>
        <li><strong>PETG-filament:</strong> Is geschikt voor prints die buiten worden gebruikt. Dit filament is zeer bestendig tegen het weer en ook hoge temperaturen.</li>
    </ul>
</div>
HTML;
}

register_activation_hook(__FILE__, 'plugin_activation_setup');

function add_3d_print_rules_styles() {
    echo '<style>
    .print-rules-container {
        max-width: 1200px;
        margin: 2rem auto;
        padding: 2rem;
        background: #fff;
        box-shadow: 0 2px 15px rgba(0,0,0,0.1);
    }

    .toc {
        background: #f8f9fa;
        padding: 1.5rem;
        border-radius: 8px;
        margin: 2rem 0;
    }

    .toc ul {
        list-style: none;
        padding-left: 0;
    }

    .toc li {
        margin: 0.5rem 0;
    }

    .toc a {
        color: #2c3e50;
        text-decoration: none;
        font-weight: 500;
    }

    .toc a:hover {
        color: #3498db;
    }

    h1, h2, h3 {
        color: #2c3e50;
    }

    .info-box {
        background: #e3f2fd;
        border-left: 4px solid #2196F3;
        padding: 1rem;
        margin: 1rem 0;
    }

    .grid {
        display: grid;
        gap: 1rem;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    }

    .tip {
        background: #fff;
        padding: 1rem;
        border-radius: 8px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    }

    .material-table {
        width: 100%;
        border-collapse: collapse;
        margin: 1rem 0;
    }

    .material-table th,
    .material-table td {
        padding: 12px;
        text-align: left;
        border-bottom: 1px solid #ddd;
    }

    .material-table th {
        background-color: #f8f9fa;
    }

    .rules-list {
        background: #fff9e6;
        padding: 1.5rem;
        border-radius: 8px;
        counter-reset: rules;
    }

    .rules-list li {
        margin: 1rem 0;
        padding-left: 2rem;
        position: relative;
    }

    .rules-list li::before {
        content: counter(rules);
        counter-increment: rules;
        position: absolute;
        left: 0;
        top: 0;
        background: #3498db;
        color: white;
        width: 1.5rem;
        height: 1.5rem;
        border-radius: 50%;
        text-align: center;
        line-height: 1.5rem;
    }
    </style>';
}
add_action('wp_head', 'add_3d_print_rules_styles');

function custom_admin_styles() {
    echo '<style>
        #custom-publish-button {
            width: 100%;
            text-align: center;
        }
        .custom-publish-box .inside {
            padding-bottom: 0;
        }
        #custom-publish-actions {
            margin: -6px -12px -12px;
        }
    </style>';
}
add_action('admin_head', 'custom_admin_styles');

function remove_publish_meta_box() {
    remove_meta_box('submitdiv', '3d_submission', 'side');
}
add_action('add_meta_boxes', 'remove_publish_meta_box', 20);

function remove_permalink_section() {
    global $post_type;
    
    if ($post_type === '3d_submission') {
        echo '<style type="text/css">
            #edit-slug-box,
            #sample-permalink {
                display: none !important;
            }
        </style>';
    }
}
add_action('admin_head', 'remove_permalink_section');

function customize_3d_submission_columns($columns) {
    unset($columns['date']);
    unset($columns['slug']);
    $columns['date'] = 'Datum';
    return $columns;
}
add_filter('manage_3d_submission_posts_columns', 'customize_3d_submission_columns');

function make_deadline_column_sortable($sortable_columns) {
    $sortable_columns['deadline'] = 'submission_deadline';
    return $sortable_columns;
}
add_filter('manage_edit-3d_submission_sortable_columns', 'make_deadline_column_sortable');

function handle_deadline_sorting($query) {
    if (!is_admin() || !$query->is_main_query()) {
        return;
    }

    $orderby = $query->get('orderby');
    if ($orderby === 'submission_deadline') {
        $query->set('meta_key', 'submission_deadline');
        $query->set('orderby', 'meta_value');
        $query->set('order', $query->get('order') ?: 'DESC');
    }
}
add_action('pre_get_posts', 'handle_deadline_sorting');

register_activation_hook(__FILE__, 'plugin_activation_setup');

function handle_3d_submission_sorting($query) {
    if (!is_admin() || !$query->is_main_query()) {
        return;
    }

    $orderby = $query->get('orderby');
    switch ($orderby) {
        case 'approval_status':
            $query->set('meta_key', 'approval_status');
            $query->set('orderby', 'meta_value');
            break;
        case '3d_file_url':
            $query->set('meta_key', '3d_file_url');
            $query->set('orderby', 'meta_value');
            break;
        case 'submission_deadline':
            $query->set('meta_key', 'submission_deadline');
            $query->set('orderby', 'meta_value_num');
            break;
    }
}
add_action('pre_get_posts', 'handle_3d_submission_sorting');

function make_3d_submission_columns_sortable($sortable_columns) {
    $sortable_columns['status'] = 'approval_status';
    $sortable_columns['file'] = '3d_file_url';
    $sortable_columns['deadline'] = 'submission_deadline';
    return $sortable_columns;
}

function fetch_hotend_id_info() {
    $printers = get_posts(array(
        'post_type' => 'printer',
        'posts_per_page' => -1,
        'meta_key' => 'printer_ip',
        'meta_compare' => 'EXISTS'
    ));
    
    if (empty($printers)) {
        return '<p>No printers configured</p>';
    }
    
    $output = '';
    
    foreach ($printers as $printer) {
        $printer_ip = get_post_meta($printer->ID, 'printer_ip', true);
        if (empty($printer_ip)) continue;
        
        $api_url = "http://{$printer_ip}/api/v1/printer/heads/0";
        $response = wp_remote_get($api_url, array('timeout' => 10));
        
        if (is_wp_error($response)) {
            $output .= '<div class="printer-status error">
                    <h3>' . esc_html($printer->post_title) . '</h3>
                    <p>Printer info could not be retrieved. Please try again later.</p>
                </div>';
            continue;
        }
        
        $body = wp_remote_retrieve_body($response);
        
        if (stripos($body, '<html') !== false) {
            $output .= '<div class="printer-status warning">
                    <h3>' . esc_html($printer->post_title) . '</h3>
                    <p>The API returned an HTML response instead of JSON. Please verify the API endpoint.</p>
                </div>';
            continue;
        }
        
        $printer_data = json_decode($body, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $output .= '<div class="printer-status error">
                    <h3>' . esc_html($printer->post_title) . '</h3>
                    <p>Error decoding printer data: ' . esc_html(json_last_error_msg()) . '</p>
                </div>';
            continue;
        }
        
        if (!isset($printer_data['extruders']) || !is_array($printer_data['extruders'])) {
            $output .= '<div class="printer-status warning">
                    <h3>' . esc_html($printer->post_title) . '</h3>
                    <p>No extruder data available.</p>
                </div>';
            continue;
        }

        $hotend_ids = array();
        foreach ($printer_data['extruders'] as $extruder) {
            if (isset($extruder['hotend']) && is_array($extruder['hotend']) && isset($extruder['hotend']['id'])) {
                $hotend_ids[] = $extruder['hotend']['id'];
            }
        }

        $unique_hotend_ids = array_unique($hotend_ids);
        
        if (empty($unique_hotend_ids)) {
            $output .= '<div class="printer-status warning">
                    <h3>' . esc_html($printer->post_title) . '</h3>
                    <p>No hotend data available.</p>
                </div>';
            continue;
        }

        $output .= '<div class="printer-status success">
                <h3>' . esc_html($printer->post_title) . ' - Printcore Info</h3>
                <ul>';
        foreach ($unique_hotend_ids as $id) {
            $output .= '<li><strong>Hotend ID:</strong> ' . esc_html($id) . '</li>';
        }
        $output .= '</ul></div>';
    }
    
    return $output;
}
add_shortcode('hotend_id_info', 'fetch_hotend_id_info');

function fetch_current_material() {
    $printers = get_posts(array(
        'post_type' => 'printer',
        'posts_per_page' => -1,
        'meta_key' => 'printer_ip',
        'meta_compare' => 'EXISTS'
    ));
    
    if (empty($printers)) {
        return '<div class="printer-status warning">
                <p>⚠️ No printers configured</p>
            </div>';
    }
    
    $output = '';
    
    foreach ($printers as $printer) {
        $printer_ip = get_post_meta($printer->ID, 'printer_ip', true);
        if (empty($printer_ip)) continue;
        
        $printer_api_url = "http://{$printer_ip}/cluster-api/v1/printers/";
        $response = wp_remote_get($printer_api_url, array(
            'timeout' => 10,
            'sslverify' => false
        ));

        if (is_wp_error($response)) {
            $output .= '<div class="printer-status error">
                    <h3>' . esc_html($printer->post_title) . '</h3>
                    <p>❌ Could not connect to printer. Please try again later.</p>
                    <p>Technical details: ' . esc_html($response->get_error_message()) . '</p>
                </div>';
            continue;
        }

        $printer_data = json_decode(wp_remote_retrieve_body($response), true);
        
        if (empty($printer_data) || !is_array($printer_data)) {
            $output .= '<div class="printer-status warning">
                    <h3>' . esc_html($printer->post_title) . '</h3>
                    <p>⚠️ Printer data unavailable. Please check printer connection.</p>
                </div>';
            continue;
        }

        $printer_info = $printer_data[0];
        
        if (!isset($printer_info['material_station']['material_slots'])) {
            $output .= '<div class="printer-status warning">
                    <h3>' . esc_html($printer->post_title) . '</h3>
                    <p>⚠️ Printer material data unavailable.</p>
                </div>';
            continue;
        }

        $current_materials = [];
        foreach ($printer_info['material_station']['material_slots'] as $slot) {
            if (!empty($slot['material']['material'])) {
                $material_name = esc_html($slot['material']['brand'] . ' ' . $slot['material']['material'] . ' (' . $slot['material']['color'] . ')');
                $material_amount = round($slot['material_remaining'] * 100, 2) . '% remaining';
                $current_materials[] = "$material_name - $material_amount";
            }
        }

        if (empty($current_materials)) {
            $output .= '<div class="printer-status info">
                    <h3>' . esc_html($printer->post_title) . '</h3>
                    <p>🔄 No material currently loaded in the printer</p>
                </div>';
            continue;
        }

        $output .= '<div class="printer-status success">
                <h3>🖨️ ' . esc_html($printer->post_title) . ' - Loaded Materials</h3>
                <ul class="material-list">
                    <li>' . implode('</li><li>', $current_materials) . '</li>
                </ul>
                <p class="timestamp">Last updated: ' . current_time('mysql') . '</p>
            </div>';
    }
    
    return $output;
}
add_shortcode('current_material', 'fetch_current_material');

function add_printer_info_styles() {
    echo '<style>
        .printer-info {
            margin: 20px 0;
            padding: 15px;
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 4px;
        }
        .printer-info h3 {
            margin-top: 0;
            color: #2c3e50;
            border-bottom: 1px solid #ddd;
            padding-bottom: 10px;
        }
        .printer-info ul {
            list-style: none;
            padding-left: 0;
        }
        .printer-status {
            margin: 15px 0;
            padding: 10px;
            border-radius: 4px;
        }
        .printer-status.error { background: #ffe6e6; border: 1px solid #ffcccc; }
        .printer-status.warning { background: #fff3e6; border: 1px solid #ffe0b3; }
        .printer-status.info { background: #e6f3ff; border: 1px solid #b3d9ff; }
        .printer-status.success { background: #e6ffe6; border: 1px solid #b3ffb3; }
    </style>';
}
add_action('wp_head', 'add_printer_info_styles');

function manage_printer_columns($columns) {
    $new_columns = [
        'cb' => $columns['cb'],
        'title' => __('Printer Name'),
        'ip_address' => __('IP Address'),
        'status' => __('Status')
    ];
    return $new_columns;
}

function populate_printer_columns($column, $post_id) {
    switch ($column) {
        case 'ip_address':
            echo esc_html(get_post_meta($post_id, 'printer_ip', true));
            break;

        case 'status':
            $printer_ip = get_post_meta($post_id, 'printer_ip', true);
            if (!$printer_ip) {
                echo '<em>No IP configured</em>';
                break;
            }
            
            $status_data = get_print_job_data($printer_ip);
            if (!$status_data) {
                echo '<span style="color: #ff0000;">Offline</span>';
                break;
            }
            
            $state = $status_data['state'] ?? 'unknown';
            echo '<span style="color: ' . get_status_color($state) . ';">';
            echo ucfirst($state);
            echo '</span>';
            break;
    }
}

function printer_admin_styles() {
    echo '<style>
        .column-status { width: 120px; }
        .column-ip_address { width: 150px; }
    </style>';
}

function send_gcode_to_printer($post_id) {
    $approval_status = get_post_meta($post_id, 'approval_status', true);
    if ($approval_status !== 'goedgekeurd') {
        return false;
    }

    $gcode_url = get_post_meta($post_id, 'gcode_file_url', true);
    if (empty($gcode_url)) {
        error_log('No G-code file for post ' . $post_id);
        return false;
    }

    $printer_id = get_post_meta($post_id, 'selected_printer', true);
    $printer_ip = get_post_meta($printer_id, 'printer_ip', true);
    $api_key = get_post_meta($printer_id, 'printer_api_key', true);
    
    if (empty($printer_ip) || empty($api_key)) {
        error_log('Printer configuration incomplete for post ' . $post_id);
        return false;
    }

    $upload_dir = wp_upload_dir();
    $file_path = str_replace($upload_dir['baseurl'], $upload_dir['basedir'], $gcode_url);
    if (!file_exists($file_path)) {
        error_log('G-code file not found: ' . $file_path);
        return false;
    }

    $headers = [
        'X-Api-Key' => $api_key,
        'Content-Type' => 'application/json',
        'Accept' => 'application/json'
    ];

    $upload_url = "http://{$printer_ip}/api/v1/files";
    $file_content = file_get_contents($file_path);
    
    $upload_response = wp_remote_post($upload_url, [
        'headers' => $headers,
        'body' => $file_content,
        'timeout' => 30
    ]);

    if (is_wp_error($upload_response)) {
        error_log('Upload failed: ' . $upload_response->get_error_message());
        return false;
    }

    $upload_status = wp_remote_retrieve_response_code($upload_response);
    if ($upload_status !== 201) {
        error_log('Upload failed. Status: ' . $upload_status . ' Response: ' . wp_remote_retrieve_body($upload_response));
        return false;
    }

    $upload_data = json_decode(wp_remote_retrieve_body($upload_response), true);
    $file_uuid = $upload_data['id'] ?? null;
    if (!$file_uuid) {
        error_log('Failed to get file UUID');
        return false;
    }

    $print_job_url = "http://{$printer_ip}/api/v1/print_job";
    $print_job_payload = [
        'file' => [
            'id' => $file_uuid,
            'path' => '/' . basename($file_path),
            'type' => 'machinecode'
        ],
        'target' => 'print_head'
    ];

    $print_response = wp_remote_post($print_job_url, [
        'headers' => $headers,
        'body' => json_encode($print_job_payload),
        'timeout' => 30
    ]);

    if (is_wp_error($print_response)) {
        error_log('Print job failed: ' . $print_response->get_error_message());
        return false;
    }

    $print_status = wp_remote_retrieve_response_code($print_response);
    if ($print_status !== 201) {
        error_log('Print job failed. Status: ' . $print_status . ' Response: ' . wp_remote_retrieve_body($print_response));
        return false;
    }

    update_post_meta($post_id, 'print_job_started', current_time('mysql'));
    error_log('Successfully started print job for post ' . $post_id);
    
    return true;
}

function render_printer_settings_meta_box($post) {
    wp_nonce_field('printer_settings_nonce', 'printer_settings_nonce');
    $ip_address = get_post_meta($post->ID, 'printer_ip', true);
    $api_key = get_post_meta($post->ID, 'printer_api_key', true);
    ?>
    <p>
        <label for="printer_ip">Printer IP Address:</label>
        <input type="text" id="printer_ip" name="printer_ip" 
               value="<?php echo esc_attr($ip_address); ?>" 
               style="width: 100%">
    </p>
    <p>
        <label for="printer_api_key">API Key:</label>
        <input type="password" id="printer_api_key" name="printer_api_key" 
               value="<?php echo esc_attr($api_key); ?>" 
               style="width: 100%">
    </p>
    <?php
}

function save_printer_settings($post_id) {
    if (!isset($_POST['printer_settings_nonce']) || 
        !wp_verify_nonce($_POST['printer_settings_nonce'], 'printer_settings_nonce')) {
        return;
    }
    
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    
    if ('printer' === $_POST['post_type'] && !current_user_can('edit_post', $post_id)) {
        return;
    }
    
    if (isset($_POST['printer_ip'])) {
        update_post_meta($post_id, 'printer_ip', sanitize_text_field($_POST['printer_ip']));
    }
    
    if (isset($_POST['printer_api_key'])) {
        update_post_meta($post_id, 'printer_api_key', sanitize_text_field($_POST['printer_api_key']));
    }
}
add_action('save_post', 'save_printer_settings');

function get_print_job_data($printer_ip) {
    $transient_key = 'printer_status_' . md5($printer_ip);
    $cached = get_transient($transient_key);
    
    if ($cached !== false) {
        return $cached;
    }
    
    $data = [
        'state' => 'offline',
        'time_total' => 0,
        'time_elapsed' => 0
    ];
    
    try {
        $response = wp_remote_get("http://{$printer_ip}/api/v1/print_job", [
            'timeout' => 3
        ]);
        
        if (is_wp_error($response)) {
            return $data;
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (isset($body['state'])) {
            $data = [
                'state' => strtolower($body['state']),
                'time_total' => isset($body['time_total']) ? intval($body['time_total']) : 0,
                'time_elapsed' => isset($body['time_elapsed']) ? intval($body['time_elapsed']) : 0
            ];
        }
    } catch (Exception $e) {
        error_log('Printer status check failed: ' . $e->getMessage());
    }
    
    set_transient($transient_key, $data, 15);
    return $data;
}

function human_readable_time($seconds) {
    $hours = floor($seconds / 3600);
    $minutes = floor(($seconds % 3600) / 60);
    $seconds = $seconds % 60;
    
    $parts = [];
    if ($hours > 0) $parts[] = sprintf('%02dh', $hours);
    if ($minutes > 0) $parts[] = sprintf('%02dm', $minutes);
    $parts[] = sprintf('%02ds', $seconds);
    
    return implode(' ', $parts);
}

function get_status_color($state) {
    switch ($state) {
        case 'printing':
            return '#00a32a';
        case 'paused':
            return '#dba617';
        case 'error':
            return '#dc3232';
        default:
            return '#949494';
    }
}

add_filter('manage_edit-3d_submission_sortable_columns', 'make_3d_submission_columns_sortable');

// Daily admin notification for pending submissions
function schedule_pending_submissions_notification() {
    if (!wp_next_scheduled('daily_pending_submissions_notification')) {
        wp_schedule_event(strtotime('tomorrow 9:00:00'), 'daily', 'daily_pending_submissions_notification');
    }
}

function send_pending_submissions_notification() {
    // Count pending submissions
    $pending_count = count(get_posts([
        'post_type' => '3d_submission',
        'posts_per_page' => -1,
        'meta_query' => [
            [
                'key' => 'approval_status',
                'value' => 'inbehandeling',
                'compare' => '='
            ]
        ]
    ]));
    
    // If there are no pending submissions, no need to send an email
    if ($pending_count <= 0) {
        return;
    }
    
    // Get all administrator emails
    $admin_emails = [];
    $admins = get_users([
        'role' => 'administrator'
    ]);
    
    foreach ($admins as $admin) {
        $admin_emails[] = $admin->user_email;
    }
    
    if (empty($admin_emails)) {
        return;
    }

    // Prepare email content
    $subject = sprintf(
        '%d 3D print %s wachten op review',
        $pending_count,
        $pending_count === 1 ? 'aanvraag' : 'aanvragen'
    );
    
    $site_url = get_site_url();
    $admin_url = admin_url('edit.php?post_type=3d_submission&approval_status=inbehandeling');
    
    $message = '<p>Beste beheerder,</p>';
    $message .= '<p>Er ' . ($pending_count === 1 ? 'is' : 'zijn') . ' momenteel <strong>' . $pending_count . '</strong> ';
    $message .= '3D print ' . ($pending_count === 1 ? 'aanvraag' : 'aanvragen') . ' die op review ';
    $message .= ($pending_count === 1 ? 'wacht' : 'wachten') . '.</p>';
    
    $message .= '<p><a href="' . esc_url($admin_url) . '">Bekijk de wachtende aanvragen</a></p>';
    
    $message .= '<hr>';
    $message .= '<p><small>Dit is een automatische melding van de 3D Print Service op ' . get_bloginfo('name') . '</small></p>';
    
    // Send email
    $headers = [
        'Content-Type: text/html; charset=UTF-8',
        'From: 3D Print Service <no-reply@' . parse_url($site_url, PHP_URL_HOST) . '>'
    ];
    
    wp_mail($admin_emails, $subject, $message, $headers);
}

// Register the hook for the notification
add_action('daily_pending_submissions_notification', 'send_pending_submissions_notification');

// Filter admin listing to show pending submissions
function filter_pending_submissions_in_admin($query) {
    if (!is_admin() || !$query->is_main_query() || !isset($_GET['post_type']) || $_GET['post_type'] !== '3d_submission') {
        return;
    }
    
    if (isset($_GET['approval_status']) && $_GET['approval_status'] === 'inbehandeling') {
        $query->set('meta_query', [
            [
                'key' => 'approval_status',
                'value' => 'inbehandeling',
                'compare' => '='
            ]
        ]);
    }
}
add_action('pre_get_posts', 'filter_pending_submissions_in_admin');

// Setup and cleanup functions for scheduling
function activate_daily_notifications() {
    schedule_pending_submissions_notification();
}

function deactivate_daily_notifications() {
    $timestamp = wp_next_scheduled('daily_pending_submissions_notification');
    if ($timestamp) {
        wp_unschedule_event($timestamp, 'daily_pending_submissions_notification');
    }
}

// Add deactivation hook
register_deactivation_hook(__FILE__, 'deactivate_daily_notifications');