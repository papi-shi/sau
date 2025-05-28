<?php
// Set header to HTML content type
header('Content-Type: text/html');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bouncing Head Effect</title>
    <style>
        body {
            margin: 0;
            padding: 0;
            overflow: hidden;
            background-color: #f0f0f0;
            height: 100vh;
        }
        #head {
            position: absolute;
            width: 100px;
            height: 100px;
            border-radius: 50%;
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
        }
    </style>
</head>
<body>
    <?php
    // You can replace this with the path to your own head image
    $imageUrl = "head_image.jpg";
    echo '<div id="head" style="background-image: url(\'' . $imageUrl . '\');"></div>';
    ?>

    <script>
        // Get the head element
        const head = document.getElementById('head');
        
        // Initial position
        let x = Math.random() * (window.innerWidth - 100);
        let y = Math.random() * (window.innerHeight - 100);
        
        // Initial velocity
        let vx = (Math.random() * 10) + 5;
        let vy = (Math.random() * 10) + 5;
        
        // Animation function
        function animate() {
            // Update position
            x += vx;
            y += vy;
            
            // Bounce off the walls
            if (x <= 0 || x >= window.innerWidth - 100) {
                vx = -vx;
                // Add some randomness
                vx += (Math.random() - 0.5) * 2;
            }
            if (y <= 0 || y >= window.innerHeight - 100) {
                vy = -vy;
                // Add some randomness
                vy += (Math.random() - 0.5) * 2;
            }
            
            // Keep velocity within reasonable bounds
            vx = Math.max(-15, Math.min(15, vx));
            vy = Math.max(-15, Math.min(15, vy));
            
            // Apply the new position
            head.style.left = x + 'px';
            head.style.top = y + 'px';
            
            // Rotate the head slightly based on direction
            const angle = Math.atan2(vy, vx) * (180 / Math.PI);
            head.style.transform = `rotate(${angle}deg)`;
            
            // Request next frame
            requestAnimationFrame(animate);
        }
        
        // Handle window resize
        window.addEventListener('resize', function() {
            // Keep the head within bounds after resize
            x = Math.min(x, window.innerWidth - 100);
            y = Math.min(y, window.innerHeight - 100);
        });
        
        // Start animation
        animate();
        
        // Add support for multiple heads on click
        document.addEventListener('click', function(e) {
            const newHead = head.cloneNode(true);
            newHead.style.left = (e.clientX - 50) + 'px';
            newHead.style.top = (e.clientY - 50) + 'px';
            document.body.appendChild(newHead);
            
            // Give the new head its own animation
            const headX = e.clientX - 50;
            const headY = e.clientY - 50;
            const headVx = (Math.random() * 16) - 8;
            const headVy = (Math.random() * 16) - 8;
            
            function animateHead() {
                // Update position
                headX += headVx;
                headY += headVy;
                
                // Bounce off edges
                if (headX <= 0 || headX >= window.innerWidth - 100) {
                    headVx = -headVx;
                }
                if (headY <= 0 || headY >= window.innerHeight - 100) {
                    headVy = -headVy;
                }
                
                // Apply position
                newHead.style.left = headX + 'px';
                newHead.style.top = headY + 'px';
                
                // Request next frame for this head
                requestAnimationFrame(animateHead);
            }
            
            animateHead();
        });
    </script>
</body>
</html>