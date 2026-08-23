<?php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/database.php';

require_role('user');
$pdo = getDB();
$user_id = $_SESSION['user_id'];

// Get user's website
$stmt = $pdo->prepare("SELECT * FROM websites WHERE user_id = ? AND deleted_at IS NULL LIMIT 1");
$stmt->execute([$user_id]);
$website = $stmt->fetch();

if (!$website) {
    set_flash_message('error', 'You need to create a website first.');
    redirect('/user/dashboard.php');
}

$website_id = $website['id'];
$template_id = $website['template_id'];

// Get current theme settings
$stmt = $pdo->prepare("SELECT * FROM website_themes WHERE website_id = ?");
$stmt->execute([$website_id]);
$current_theme = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

// Get template details and capabilities
$template_stmt = $pdo->prepare("SELECT * FROM templates WHERE id = ?");
$template_stmt->execute([$template_id]);
$template = $template_stmt->fetch();

$manifest = [];
if ($template) {
    $manifest_path = __DIR__ . '/../templates/' . $template['folder_key'] . '/template.json';
    if (file_exists($manifest_path)) {
        $manifest = json_decode(file_get_contents($manifest_path), true) ?: [];
    }
}

$capabilities = $manifest['supports']['theme'] ?? [];
$defaults = $manifest['theme_defaults'] ?? [];

// Allowed options based on specifications
$allowed_fonts = [
    'Poppins', 'Inter', 'Playfair Display', 'DM Sans', 'Montserrat',
    'Cormorant Garamond', 'Lora', 'Manrope', 'Outfit', 'Libre Baskerville', 'Lato'
];
$allowed_scales = ['Small', 'Medium', 'Large'];
$allowed_buttons = ['Sharp', 'Soft', 'Rounded', 'Pill'];
$allowed_shadows = ['None', 'Subtle', 'Medium', 'Soft Luxury'];
$allowed_cards = ['Flat', 'Soft', 'Elevated', 'Bordered', 'Luxury'];
$allowed_scales = ['Small', 'Medium', 'Large'];
$allowed_hero = ['Centered', 'Image Left', 'Image Right', 'Overlay', 'Full Width'];
$allowed_nav = ['Minimal', 'Logo Left', 'Logo Center', 'Sticky'];
$allowed_colors = [
    'primary_color', 'secondary_color', 'accent_color', 'background_color',
    'surface_color', 'text_color', 'heading_color', 'muted_color',
    'button_color', 'button_text_color', 'border_color'
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        set_flash_message('error', 'Invalid security token.');
        redirect('/user/theme.php');
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'reset') {
        $stmt = $pdo->prepare("DELETE FROM website_themes WHERE website_id = ?");
        $stmt->execute([$website_id]);
        log_activity($pdo, $user_id, null, 'theme_reset', 'website', $website_id);
        set_flash_message('success', 'Theme has been reset to defaults.');
        redirect('/user/theme.php');
    } elseif ($action === 'save') {
        $updates = [];

        // Validate Colors
        foreach ($allowed_colors as $color) {
            if (isset($_POST[$color])) {
                $val = trim($_POST[$color]);
                if ($val === '') {
                    $updates[$color] = null;
                } elseif (preg_match('/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/', $val)) {
                    $updates[$color] = $val;
                }
            }
        }

        // Validate Typography
        if (isset($_POST['heading_font']) && in_array($_POST['heading_font'], $allowed_fonts)) $updates['heading_font'] = $_POST['heading_font'];
        if (isset($_POST['body_font']) && in_array($_POST['body_font'], $allowed_fonts)) $updates['body_font'] = $_POST['body_font'];

        // Validate Styles
        if (isset($_POST['border_radius']) && in_array($_POST['border_radius'], $allowed_buttons)) $updates['border_radius'] = $_POST['border_radius'];
        if (isset($_POST['shadow_style']) && in_array($_POST['shadow_style'], $allowed_shadows)) $updates['shadow_style'] = $_POST['shadow_style'];

        if (empty($current_theme)) {
            $cols = ['website_id'];
            $vals = [$website_id];
            $placeholders = ['?'];
            foreach ($updates as $k => $v) {
                $cols[] = $k;
                $vals[] = $v;
                $placeholders[] = '?';
            }
            $sql = "INSERT INTO website_themes (" . implode(', ', $cols) . ") VALUES (" . implode(', ', $placeholders) . ")";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($vals);
        } else {
            $set_clauses = [];
            $vals = [];
            foreach ($updates as $k => $v) {
                $set_clauses[] = "$k = ?";
                $vals[] = $v;
            }
            if (!empty($set_clauses)) {
                $vals[] = $website_id;
                $sql = "UPDATE website_themes SET " . implode(', ', $set_clauses) . " WHERE website_id = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute($vals);
            }
        }

        log_activity($pdo, $user_id, null, 'theme_updated', 'website', $website_id);
        set_flash_message('success', 'Theme updated successfully.');
        redirect('/user/theme.php');
    }
}

// Function to get current value (from DB, or default)
function get_theme_value($key, $current_theme, $defaults, $section = 'colors') {
    if (isset($current_theme[$key]) && $current_theme[$key] !== null) {
        return escape($current_theme[$key]);
    }
    return escape($defaults[$section][$key] ?? '');
}

?>
<?php require_once __DIR__ . '/../includes/user_header.php'; ?>

<style>
    .color-picker-wrapper {
        position: relative;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    .color-picker-input {
        width: 40px;
        height: 40px;
        padding: 0;
        border: 1px solid #ddd;
        border-radius: 8px;
        cursor: pointer;
        background: none;
    }
    .color-hex-input {
        flex: 1;
        font-family: monospace;
    }

    .editor-layout {
        display: flex;
        gap: 20px;
        height: calc(100vh - 180px);
        min-height: 600px;
    }

    .editor-controls {
        width: 350px;
        flex-shrink: 0;
        background: #fff;
        border-radius: 10px;
        box-shadow: 0 4px 6px rgba(0,0,0,0.05);
        display: flex;
        flex-direction: column;
        overflow: hidden;
    }

    .editor-preview {
        flex-grow: 1;
        background: #f8f9fa;
        border-radius: 10px;
        border: 1px solid #dee2e6;
        overflow: hidden;
        position: relative;
    }

    .preview-iframe {
        width: 100%;
        height: 100%;
        border: none;
    }

    .controls-body {
        overflow-y: auto;
        padding: 20px;
        flex-grow: 1;
    }

    .controls-footer {
        padding: 15px 20px;
        background: #f8f9fa;
        border-top: 1px solid #dee2e6;
        display: flex;
        justify-content: space-between;
        gap: 10px;
    }

    @media (max-width: 992px) {
        .editor-layout { flex-direction: column; height: auto; }
        .editor-controls { width: 100%; }
        .editor-preview { height: 600px; }
    }
</style>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="h3 mb-0 text-gray-800">Appearance</h2>
</div>

<div class="editor-layout">
    <!-- Controls -->
    <div class="editor-controls">
        <form method="POST" action="" id="themeForm" class="d-flex flex-column h-100">
            <?php csrf_field(); ?>
            <input type="hidden" name="action" value="save">

            <div class="controls-body">
                <!-- Color Section -->
                <?php if (!empty($capabilities['colors'])): ?>
                <div class="accordion" id="themeAccordion">
                    <div class="accordion-item border-0 border-bottom">
                        <h2 class="accordion-header">
                            <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#colorsCollapse">
                                <strong>Colors</strong>
                            </button>
                        </h2>
                        <div id="colorsCollapse" class="accordion-collapse collapse show" data-bs-parent="#themeAccordion">
                            <div class="accordion-body px-0">
                                <?php
                                $color_fields = [
                                    'primary_color' => 'Primary Color',
                                    'background_color' => 'Background',
                                    'surface_color' => 'Surface / Card',
                                    'text_color' => 'Text Color',
                                    'heading_color' => 'Heading Color',
                                    'button_color' => 'Button Color',
                                    'button_text_color' => 'Button Text'
                                ];
                                foreach ($color_fields as $key => $label):
                                    $val = get_theme_value($key, $current_theme, $defaults, 'colors');
                                ?>
                                <div class="mb-3 px-3">
                                    <label class="form-label small text-muted fw-bold"><?= $label ?></label>
                                    <div class="color-picker-wrapper">
                                        <input type="color" class="color-picker-input theme-input" data-var="--<?= str_replace('_', '-', $key) ?>" value="<?= $val ?>">
                                        <input type="text" name="<?= $key ?>" class="form-control color-hex-input theme-input-sync" value="<?= $val ?>" pattern="^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$" placeholder="#000000">
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                <!-- Typography Section -->
                <?php if (!empty($capabilities['typography'])): ?>
                    <div class="accordion-item border-0 border-bottom">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#typeCollapse">
                                <strong>Typography</strong>
                            </button>
                        </h2>
                        <div id="typeCollapse" class="accordion-collapse collapse" data-bs-parent="#themeAccordion">
                            <div class="accordion-body px-3">
                                <div class="mb-3">
                                    <label class="form-label small text-muted fw-bold">Heading Font</label>
                                    <select name="heading_font" class="form-select font-select" data-var="--heading-font">
                                        <?php
                                        $curr = get_theme_value('heading_font', $current_theme, $defaults, 'typography');
                                        foreach ($allowed_fonts as $font):
                                        ?>
                                        <option value="<?= $font ?>" <?= $curr === $font ? 'selected' : '' ?>><?= $font ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label small text-muted fw-bold">Body Font</label>
                                    <select name="body_font" class="form-select font-select" data-var="--body-font">
                                        <?php
                                        $curr = get_theme_value('body_font', $current_theme, $defaults, 'typography');
                                        foreach ($allowed_fonts as $font):
                                        ?>
                                        <option value="<?= $font ?>" <?= $curr === $font ? 'selected' : '' ?>><?= $font ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                                                <div class="mb-3">
                                    <label class="form-label small text-muted fw-bold">Heading Size Scale</label>
                                    <select name="heading_scale" class="form-select style-select" data-var="--heading-scale" data-type="heading_scale">
                                        <?php
                                        $curr = get_theme_value('heading_scale', $current_theme, $defaults, 'typography');
                                        foreach ($allowed_scales as $scale):
                                        ?>
                                        <option value="<?= $scale ?>" <?= $curr === $scale ? 'selected' : '' ?>><?= $scale ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label small text-muted fw-bold">Body Size Scale</label>
                                    <select name="body_scale" class="form-select style-select" data-var="--body-scale" data-type="body_scale">
                                        <?php
                                        $curr = get_theme_value('body_scale', $current_theme, $defaults, 'typography');
                                        foreach ($allowed_scales as $scale):
                                        ?>
                                        <option value="<?= $scale ?>" <?= $curr === $scale ? 'selected' : '' ?>><?= $scale ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Styles Section -->
                <?php if (!empty($capabilities['buttons']) || !empty($capabilities['cards'])): ?>
                    <div class="accordion-item border-0">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#styleCollapse">
                                <strong>Shapes & Shadows</strong>
                            </button>
                        </h2>
                        <div id="styleCollapse" class="accordion-collapse collapse" data-bs-parent="#themeAccordion">
                            <div class="accordion-body px-3">
                                <div class="mb-3">
                                    <label class="form-label small text-muted fw-bold">Corners (Border Radius)</label>
                                    <select name="border_radius" class="form-select style-select" data-var="--border-radius" data-type="border_radius">
                                        <?php
                                        $curr = get_theme_value('border_radius', $current_theme, $defaults, 'styles');
                                        foreach ($allowed_buttons as $style):
                                        ?>
                                        <option value="<?= $style ?>" <?= $curr === $style ? 'selected' : '' ?>><?= $style ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label small text-muted fw-bold">Shadow Style</label>
                                    <select name="shadow_style" class="form-select style-select" data-var="--shadow-style" data-type="shadow_style">
                                        <?php
                                        $curr = get_theme_value('shadow_style', $current_theme, $defaults, 'styles');
                                        foreach ($allowed_shadows as $style):
                                        ?>
                                        <option value="<?= $style ?>" <?= $curr === $style ? 'selected' : '' ?>><?= $style ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
                </div>
                <?php else: ?>
                <div class="alert alert-warning m-3">
                    This template does not support custom theme capabilities.
                </div>
                <?php endif; ?>
            </div>

            <div class="controls-footer">
                <button type="button" class="btn btn-outline-danger btn-sm" onclick="document.getElementById('resetForm').submit();">Reset</button>
                <button type="submit" class="btn btn-primary btn-sm px-4">Save Changes</button>
            </div>
        </form>

        <form method="POST" action="" id="resetForm" class="d-none">
            <?php csrf_field(); ?>
            <input type="hidden" name="action" value="reset">
        </form>
    </div>

    <!-- Live Preview -->
    <div class="editor-preview d-flex flex-column">
        <div class="bg-dark text-white p-2 text-center small fw-bold d-flex justify-content-between px-4 align-items-center">
            <span>Live Preview</span>
            <a href="/public/site.php?website_id=<?= $website_id ?>" target="_blank" class="text-white text-decoration-none">
                <i class="bi bi-box-arrow-up-right me-1"></i> Open Full
            </a>
        </div>
        <iframe src="/public/site.php?website_id=<?= $website_id ?>" class="preview-iframe" id="previewIframe"></iframe>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const iframe = document.getElementById('previewIframe');

        // Sync color picker and text input
        document.querySelectorAll('.color-picker-input').forEach(input => {
            input.addEventListener('input', function() {
                const hexInput = this.nextElementSibling;
                hexInput.value = this.value;
                updateIframeVar(this.dataset.var, this.value);
            });
        });

        document.querySelectorAll('.color-hex-input').forEach(input => {
            input.addEventListener('input', function() {
                const picker = this.previousElementSibling;
                if (/^#[0-9A-F]{6}$/i.test(this.value)) {
                    picker.value = this.value;
                    updateIframeVar(picker.dataset.var, this.value);
                }
            });
        });

        // Send font updates
        const fontMap = {
            'Poppins': "'Poppins', sans-serif",
            'Inter': "'Inter', sans-serif",
            'Playfair Display': "'Playfair Display', serif",
            'DM Sans': "'DM Sans', sans-serif",
            'Montserrat': "'Montserrat', sans-serif",
            'Cormorant Garamond': "'Cormorant Garamond', serif",
            'Lora': "'Lora', serif",
            'Manrope': "'Manrope', sans-serif",
            'Outfit': "'Outfit', sans-serif",
            'Libre Baskerville': "'Libre Baskerville', serif",
            'Lato': "'Lato', sans-serif"
        };

        document.querySelectorAll('.font-select').forEach(select => {
            select.addEventListener('change', function() {
                const cssVal = fontMap[this.value];
                if (cssVal) {
                    updateIframeVar(this.dataset.var, cssVal);
                    // Could also postMessage to inject the font URL dynamically if we want perfect preview,
                    // but most likely we'll just apply the family and hope it's loaded or falls back.
                }
            });
        });

        // Send style updates
        const styleMap = {
            'border_radius': {
                'Sharp': '0px',
                'Soft': '4px',
                'Rounded': '8px',
                'Pill': '9999px'
            },
            'shadow_style': {
                'None': 'none',
                'Subtle': '0 2px 4px rgba(0,0,0,0.05)',
                'Medium': '0 4px 6px rgba(0,0,0,0.1)',
                'Soft Luxury': '0 10px 30px rgba(0,0,0,0.08)'
            },
            'heading_scale': {
                'Small': '0.9',
                'Medium': '1',
                'Large': '1.1'
            },
            'body_scale': {
                'Small': '0.9',
                'Medium': '1',
                'Large': '1.1'
            }
        };

        document.querySelectorAll('.style-select').forEach(select => {
            select.addEventListener('change', function() {
                const type = this.dataset.type;
                const cssVal = styleMap[type][this.value];
                if (cssVal !== undefined) {
                    updateIframeVar(this.dataset.var, cssVal);
                }
            });
        });

        function updateIframeVar(cssVar, val) {
            if (iframe.contentWindow) {
                // Post message to the iframe to update its CSS variables
                iframe.contentWindow.postMessage({
                    type: 'UPDATE_CSS_VAR',
                    variable: cssVar,
                    value: val
                }, '*');
            }
        }
    });
</script>

<?php require_once __DIR__ . '/../includes/user_footer.php'; ?>
