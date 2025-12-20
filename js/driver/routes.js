console.log('TEST: routes.js loaded successfully');

// Wait for everything to load
window.addEventListener('load', function() {
    console.log('Page fully loaded');
    
    // Hide loading message
    const loading = document.getElementById('mapLoading');
    if (loading) {
        loading.style.display = 'none';
        console.log('Hid loading indicator');
    }
    
    // Test Leaflet
    if (typeof L === 'undefined') {
        console.error('ERROR: Leaflet not loaded!');
        if (loading) {
            loading.innerHTML = 'ERROR: Map library failed to load';
            loading.style.display = 'flex';
        }
        return;
    }
    
    console.log('SUCCESS: Leaflet is loaded:', L);
    
    // Create the map
    try {
        console.log('Creating map...');
        const map = L.map('map').setView([10.3157, 123.8854], 13);
        console.log('Map created!');
        
        // Add tiles
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© OpenStreetMap'
        }).addTo(map);
        
        // Add marker
        L.marker([10.3157, 123.8854])
            .addTo(map)
            .bindPopup('Hello! Map is working!')
            .openPopup();
            
        console.log('SUCCESS: Map should be visible now');
        
        // Show success message
        alert('Map loaded successfully! Check console for details.');
        
    } catch (error) {
        console.error('ERROR creating map:', error);
        if (loading) {
            loading.innerHTML = 'ERROR: ' + error.message;
            loading.style.display = 'flex';
        }
    }
});