(function () {
    'use strict';

    var scene = document.createElement('div');
    scene.id = 'scene';
    scene.innerHTML =
        '<div class="sky"></div>' +
        '<div class="ground"></div>' +
        '<div id="stars-container"></div>';
    document.body.insertBefore(scene, document.body.firstChild);

    var starsEl = document.getElementById('stars-container');
    for (var i = 0; i < 95; i++) {
        var s = document.createElement('div');
        s.className = 'star';
        s.style.left   = (Math.random() * 100).toFixed(2) + '%';
        s.style.top    = (Math.random() * 62).toFixed(2)  + '%';
        var sz = (Math.random() * 2.4 + 0.8).toFixed(2);
        s.style.width  = sz + 'px';
        s.style.height = sz + 'px';
        s.style.setProperty('--dur', (Math.random() * 2.5 + 1.5).toFixed(2) + 's');
        s.style.animationDelay = (Math.random() * 5).toFixed(2) + 's';
        starsEl.appendChild(s);
    }
}());
