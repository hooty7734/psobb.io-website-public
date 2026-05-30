// js/character_3d_viewer.js
/**
 * PSOBB 3D Holographic Character Scanner & Viewer
 * 
 * Intercepts binary Sega .nj models directly from the quest editor,
 * dynamically extracts high-precision coordinates using a float scanner,
 * and renders a glowing, spinning 3D holographic particle cloud in real-time WebGL.
 */

window.Character3DViewer = (() => {
    let scene = null;
    let camera = null;
    let renderer = null;
    let pointsObject = null;
    let gridHelper = null;
    let animationFrameId = null;
    let canvas = null;
    let container = null;
    let fallbackImg = null;
    
    let isDragging = false;
    let previousMousePosition = { x: 0, y: 0 };
    let rotationSpeedX = 0;
    let rotationSpeedY = 0;
    
    // Cleanup any existing Three.js context and objects cleanly to prevent leaks
    function disposeViewer() {
        if (animationFrameId) {
            cancelAnimationFrame(animationFrameId);
            animationFrameId = null;
        }
        
        // Remove interactive listeners
        if (canvas) {
            canvas.removeEventListener('mousedown', onMouseDown);
            canvas.removeEventListener('mousemove', onMouseMove);
            canvas.removeEventListener('touchstart', onTouchStart);
            canvas.removeEventListener('touchmove', onTouchMove);
        }
        
        if (pointsObject) {
            if (scene) scene.remove(pointsObject);
            if (pointsObject.geometry) pointsObject.geometry.dispose();
            if (pointsObject.material) pointsObject.material.dispose();
            pointsObject = null;
        }
        
        if (gridHelper) {
            if (scene) scene.remove(gridHelper);
            gridHelper = null;
        }
        
        if (renderer) {
            renderer.dispose();
            renderer = null;
        }
        
        scene = null;
        camera = null;
        
        if (canvas) {
            canvas.style.display = 'none';
        }
        if (fallbackImg) {
            fallbackImg.style.display = 'block';
        }
    }
    
    // Binary Sega .nj Float scanning parser
    function parseNinjaVertices(arrayBuffer) {
        const floatView = new Float32Array(arrayBuffer);
        const points = [];
        
        // Scan with float step of 1 to ensure we capture all aligned and unaligned vertex blocks
        for (let i = 0; i < floatView.length - 2; i++) {
            const x = floatView[i];
            const y = floatView[i + 1];
            const z = floatView[i + 2];
            
            // 1. Humanoid bounds: width and depth between -12 and 12, height between -15 and 15
            if (x > -12.0 && x < 12.0 && y > -15.0 && y < 15.0 && z > -12.0 && z < 12.0) {
                // 2. Filter out matrix scales, identities, and garbage exact integers
                if (Math.abs(x) > 0.005 && Math.abs(y) > 0.005 && Math.abs(z) > 0.005) {
                    if (Math.abs(x - 1.0) > 0.005 && Math.abs(y - 1.0) > 0.005 && Math.abs(z - 1.0) > 0.005) {
                        if (Math.abs(x - 2.0) > 0.005 && Math.abs(y - 2.0) > 0.005 && Math.abs(z - 2.0) > 0.005) {
                            points.push(x, y, z);
                        }
                    }
                }
            }
        }
        
        return new Float32Array(points);
    }
    
    // Main loader and renderer pipeline
    async function loadClassModel(className) {
        canvas = document.getElementById('char-profile-3d-canvas');
        container = document.getElementById('char-profile-avatar-container');
        fallbackImg = document.getElementById('char-profile-avatar-fallback');
        
        // Dynamic fallback update in case WebGL fails
        if (fallbackImg) {
            fallbackImg.src = `/img/classes/${className.toLowerCase()}.png`;
            fallbackImg.onerror = () => { fallbackImg.src = '/img/favicon.svg'; };
        }
        
        // Proactively clean up existing WebGL elements
        disposeViewer();
        
        if (!canvas || !container) return;
        
        try {
            // Fetch .nj mesh file directly from quest editor assets
            const response = await fetch(`/quest-editor/assets/player/${className}Body.nj`);
            if (!response.ok) throw new Error(`Mesh file not found for class ${className}`);
            
            const arrayBuffer = await response.arrayBuffer();
            const vertices = parseNinjaVertices(arrayBuffer);
            
            if (vertices.length === 0) throw new Error("No valid vertices found in mesh");
            
            // Show canvas and hide fallback image
            canvas.style.display = 'block';
            if (fallbackImg) fallbackImg.style.display = 'none';
            
            // WebGL Setup
            const width = container.clientWidth || 220;
            const height = container.clientHeight || 220;
            
            scene = new THREE.Scene();
            
            // Cyan fog for depth styling
            scene.fog = new THREE.FogExp2(0x050a14, 0.035);
            
            camera = new THREE.PerspectiveCamera(45, width / height, 0.1, 100);
            
            renderer = new THREE.WebGLRenderer({
                canvas: canvas,
                antialias: true,
                alpha: true
            });
            renderer.setSize(width, height);
            renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));
            
            // Construct Particle Cloud Geometry
            const geometry = new THREE.BufferGeometry();
            geometry.setAttribute('position', new THREE.BufferAttribute(vertices, 3));
            
            // Auto-center the character geometry model
            geometry.computeBoundingBox();
            const center = new THREE.Vector3();
            geometry.boundingBox.getCenter(center);
            geometry.translate(-center.x, -center.y, -center.z);
            
            // Neon cyan glowing points material
            const material = new THREE.PointsMaterial({
                color: 0x00ffff,
                size: 0.12,
                transparent: true,
                opacity: 0.85,
                blending: THREE.AdditiveBlending,
                sizeAttenuation: true
            });
            
            pointsObject = new THREE.Points(geometry, material);
            scene.add(pointsObject);
            
            // Add a beautiful cybernetic grid helper at the feet
            const boundingBox = geometry.boundingBox;
            const modelHeight = boundingBox.max.y - boundingBox.min.y;
            
            gridHelper = new THREE.GridHelper(8, 16, 0x00ffff, 0x003333);
            gridHelper.position.y = -modelHeight / 2 - 0.1;
            gridHelper.material.transparent = true;
            gridHelper.material.opacity = 0.3;
            scene.add(gridHelper);
            
            // Set Camera dynamically based on bounding box
            const size = new THREE.Vector3();
            boundingBox.getSize(size);
            const maxDim = Math.max(size.x, size.y, size.z);
            const fov = camera.fov * (Math.PI / 180);
            let cameraZ = Math.abs(maxDim / 2 / Math.tan(fov / 2));
            cameraZ *= 1.25; // Add margin
            
            camera.position.set(0, 0, cameraZ);
            camera.lookAt(0, 0, 0);
            
            // Initial animation state variables
            isDragging = false;
            previousMousePosition = { x: 0, y: 0 };
            
            // Attach drag controls
            canvas.addEventListener('mousedown', onMouseDown);
            canvas.addEventListener('mousemove', onMouseMove);
            canvas.addEventListener('touchstart', onTouchStart, { passive: true });
            canvas.addEventListener('touchmove', onTouchMove, { passive: true });
            
            animate();
            
        } catch (e) {
            console.warn(`[3D Viewer] Falling back to 2D image because: ${e.message}`);
            disposeViewer();
        }
    }
    
    // Animation tick
    function animate() {
        if (!scene || !camera || !renderer) return;
        
        animationFrameId = requestAnimationFrame(animate);
        
        // Slowly spin automatically when not actively dragging
        if (!isDragging && pointsObject) {
            pointsObject.rotation.y += 0.005;
            if (gridHelper) gridHelper.rotation.y -= 0.002; // Rotate grid opposite way for visual wow
        }
        
        renderer.render(scene, camera);
    }
    
    // Interactive mouse listeners
    function onMouseDown(e) {
        isDragging = true;
        previousMousePosition = { x: e.offsetX, y: e.offsetY };
        window.addEventListener('mouseup', onMouseUp);
    }
    
    function onMouseMove(e) {
        if (!isDragging || !pointsObject) return;
        
        const deltaMove = {
            x: e.offsetX - previousMousePosition.x,
            y: e.offsetY - previousMousePosition.y
        };
        
        pointsObject.rotation.y += deltaMove.x * 0.008;
        pointsObject.rotation.x += deltaMove.y * 0.008;
        
        // Clamp X rotation to prevent completely flipping upside down
        pointsObject.rotation.x = Math.max(-Math.PI / 3, Math.min(Math.PI / 3, pointsObject.rotation.x));
        
        previousMousePosition = { x: e.offsetX, y: e.offsetY };
    }
    
    function onMouseUp() {
        isDragging = false;
        window.removeEventListener('mouseup', onMouseUp);
    }
    
    // Touch support for mobile browsers
    function onTouchStart(e) {
        if (e.touches.length === 1) {
            isDragging = true;
            const touch = e.touches[0];
            const rect = canvas.getBoundingClientRect();
            previousMousePosition = {
                x: touch.clientX - rect.left,
                y: touch.clientY - rect.top
            };
            window.addEventListener('touchend', onTouchEnd);
        }
    }
    
    function onTouchMove(e) {
        if (!isDragging || !pointsObject || e.touches.length !== 1) return;
        
        const touch = e.touches[0];
        const rect = canvas.getBoundingClientRect();
        const mouseX = touch.clientX - rect.left;
        const mouseY = touch.clientY - rect.top;
        
        const deltaMove = {
            x: mouseX - previousMousePosition.x,
            y: mouseY - previousMousePosition.y
        };
        
        pointsObject.rotation.y += deltaMove.x * 0.01;
        pointsObject.rotation.x += deltaMove.y * 0.01;
        
        pointsObject.rotation.x = Math.max(-Math.PI / 3, Math.min(Math.PI / 3, pointsObject.rotation.x));
        
        previousMousePosition = { x: mouseX, y: mouseY };
    }
    
    function onTouchEnd() {
        isDragging = false;
        window.removeEventListener('touchend', onTouchEnd);
    }
    
    // Auto-resize handler
    window.addEventListener('resize', () => {
        if (!renderer || !camera || !container || !canvas) return;
        const width = container.clientWidth;
        const height = container.clientHeight;
        camera.aspect = width / height;
        camera.updateProjectionMatrix();
        renderer.setSize(width, height);
    });
    
    return {
        loadClassModel: loadClassModel,
        disposeViewer: disposeViewer
    };
})();
