<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pool/Billiard Game - 8Ball & 9Ball</title>
    <style>
        body {
            margin: 0;
            padding: 20px;
            font-family: Arial, sans-serif;
            background: #2c5f30;
            color: white;
        }
        
        .game-container {
            max-width: 1200px;
            margin: 0 auto;
            text-align: center;
        }
        
        .game-controls {
            margin-bottom: 20px;
        }
        
        button {
            padding: 10px 20px;
            margin: 5px;
            background: #4CAF50;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
        }
        
        button:hover {
            background: #45a049;
        }
        
        button:disabled {
            background: #666;
            cursor: not-allowed;
        }
        
        .pool-table {
            width: 800px;
            height: 400px;
            background: #0f4d0f;
            border: 15px solid #8B4513;
            border-radius: 20px;
            margin: 0 auto;
            position: relative;
            box-shadow: 0 0 20px rgba(0,0,0,0.5);
        }
        
        .pocket {
            width: 30px;
            height: 30px;
            background: #000;
            border-radius: 50%;
            position: absolute;
        }
        
        .pocket.corner-top-left { top: -15px; left: -15px; }
        .pocket.corner-top-right { top: -15px; right: -15px; }
        .pocket.corner-bottom-left { bottom: -15px; left: -15px; }
        .pocket.corner-bottom-right { bottom: -15px; right: -15px; }
        .pocket.side-top { top: -15px; left: 50%; margin-left: -15px; }
        .pocket.side-bottom { bottom: -15px; left: 50%; margin-left: -15px; }
        
        .ball {
            width: 20px;
            height: 20px;
            border-radius: 50%;
            position: absolute;
            border: 2px solid #333;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            font-weight: bold;
        }
        
        .ball.cue { background: white; color: black; }
        .ball.ball-1 { background: #ffff00; color: black; }
        .ball.ball-2 { background: #0000ff; color: white; }
        .ball.ball-3 { background: #ff0000; color: white; }
        .ball.ball-4 { background: #800080; color: white; }
        .ball.ball-5 { background: #ffa500; color: black; }
        .ball.ball-6 { background: #008000; color: white; }
        .ball.ball-7 { background: #800000; color: white; }
        .ball.ball-8 { background: #000000; color: white; }
        .ball.ball-9 { background: linear-gradient(45deg, #ffff00 50%, white 50%); color: black; }
        .ball.ball-10 { background: linear-gradient(45deg, #0000ff 50%, white 50%); color: white; }
        .ball.ball-11 { background: linear-gradient(45deg, #ff0000 50%, white 50%); color: white; }
        .ball.ball-12 { background: linear-gradient(45deg, #800080 50%, white 50%); color: white; }
        .ball.ball-13 { background: linear-gradient(45deg, #ffa500 50%, white 50%); color: black; }
        .ball.ball-14 { background: linear-gradient(45deg, #008000 50%, white 50%); color: white; }
        .ball.ball-15 { background: linear-gradient(45deg, #800000 50%, white 50%); color: white; }
        
        .power-meter {
            width: 200px;
            height: 20px;
            background: #333;
            border: 2px solid #666;
            margin: 20px auto;
            position: relative;
        }
        
        .power-bar {
            height: 100%;
            background: linear-gradient(to right, green, yellow, red);
            width: 0%;
            transition: width 0.1s;
        }
        
        .aim-line {
            position: absolute;
            width: 2px;
            background: rgba(255, 255, 255, 0.7);
            transform-origin: bottom;
            display: none;
        }
        
        .game-info {
            display: flex;
            justify-content: space-around;
            margin: 20px 0;
            background: rgba(0,0,0,0.3);
            padding: 15px;
            border-radius: 10px;
        }
        
        .player-info {
            text-align: center;
        }
        
        .current-player {
            background: rgba(76, 175, 80, 0.3);
            padding: 10px;
            border-radius: 5px;
        }
        
        .pocketed-balls {
            display: flex;
            flex-wrap: wrap;
            gap: 5px;
            margin-top: 5px;
        }
        
        .pocketed-ball {
            width: 15px;
            height: 15px;
            border-radius: 50%;
            border: 1px solid #333;
        }
        
        .game-mode {
            margin-bottom: 10px;
            font-size: 18px;
            font-weight: bold;
        }
        
        .winner-message {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: #4CAF50;
            color: white;
            padding: 30px;
            border-radius: 10px;
            font-size: 24px;
            font-weight: bold;
            display: none;
            z-index: 1000;
        }
    </style>
</head>
<body>
    <div class="game-container">
        <h1>Pool/Billiard Game</h1>
        
        <div class="game-controls">
            <button onclick="startGame('8ball')">8-Ball</button>
            <button onclick="startGame('9ball')">9-Ball</button>
            <button onclick="resetGame()">Reset Game</button>
        </div>
        
        <div class="game-mode" id="gameMode">Select a game mode</div>
        
        <div class="game-info" id="gameInfo" style="display: none;">
            <div class="player-info" id="player1Info">
                <h3>Player 1</h3>
                <div>Group: <span id="player1Group">-</span></div>
                <div class="pocketed-balls" id="player1Balls"></div>
            </div>
            <div class="player-info" id="currentPlayerInfo">
                <div class="current-player" id="currentPlayer">Player 1's Turn</div>
                <div>Shots Remaining: <span id="shotsRemaining">1</span></div>
            </div>
            <div class="player-info" id="player2Info">
                <h3>Player 2</h3>
                <div>Group: <span id="player2Group">-</span></div>
                <div class="pocketed-balls" id="player2Balls"></div>
            </div>
        </div>
        
        <div class="pool-table" id="poolTable">
            <!-- Pockets -->
            <div class="pocket corner-top-left"></div>
            <div class="pocket corner-top-right"></div>
            <div class="pocket corner-bottom-left"></div>
            <div class="pocket corner-bottom-right"></div>
            <div class="pocket side-top"></div>
            <div class="pocket side-bottom"></div>
            
            <!-- Aim line -->
            <div class="aim-line" id="aimLine"></div>
        </div>
        
        <div class="power-meter">
            <div class="power-bar" id="powerBar"></div>
        </div>
        
        <div style="margin-top: 20px;">
            <p>Instructions:</p>
            <p>1. Click on the cue ball to start aiming</p>
            <p>2. Move your mouse to aim</p>
            <p>3. Click and hold to charge power</p>
            <p>4. Release to shoot</p>
        </div>
    </div>
    
    <div class="winner-message" id="winnerMessage"></div>

    <script>
        class PoolGame {
            constructor() {
                this.gameMode = null;
                this.currentPlayer = 1;
                this.shotsRemaining = 1;
                this.balls = [];
                this.isAiming = false;
                this.isCharging = false;
                this.power = 0;
                this.aimAngle = 0;
                this.cueBall = null;
                this.table = document.getElementById('poolTable');
                this.powerBar = document.getElementById('powerBar');
                this.aimLine = document.getElementById('aimLine');
                this.gameStarted = false;
                
                // Player groups for 8-ball
                this.player1Group = null; // 'solids' or 'stripes'
                this.player2Group = null;
                this.pocketedBalls = { player1: [], player2: [] };
                
                this.initializeEvents();
            }
            
            initializeEvents() {
                this.table.addEventListener('click', this.handleTableClick.bind(this));
                this.table.addEventListener('mousemove', this.handleMouseMove.bind(this));
                this.table.addEventListener('mousedown', this.handleMouseDown.bind(this));
                this.table.addEventListener('mouseup', this.handleMouseUp.bind(this));
                document.addEventListener('mouseup', this.handleMouseUp.bind(this));
            }
            
            startGame(mode) {
                this.gameMode = mode;
                this.currentPlayer = 1;
                this.shotsRemaining = 1;
                this.player1Group = null;
                this.player2Group = null;
                this.pocketedBalls = { player1: [], player2: [] };
                this.gameStarted = true;
                
                document.getElementById('gameMode').textContent = mode === '8ball' ? '8-Ball Game' : '9-Ball Game';
                document.getElementById('gameInfo').style.display = 'flex';
                
                this.setupBalls(mode);
                this.updateUI();
            }
            
            setupBalls(mode) {
                this.balls = [];
                this.table.innerHTML = `
                    <div class="pocket corner-top-left"></div>
                    <div class="pocket corner-top-right"></div>
                    <div class="pocket corner-bottom-left"></div>
                    <div class="pocket corner-bottom-right"></div>
                    <div class="pocket side-top"></div>
                    <div class="pocket side-bottom"></div>
                    <div class="aim-line" id="aimLine"></div>
                `;
                this.aimLine = document.getElementById('aimLine');
                
                // Create cue ball
                this.cueBall = this.createBall('cue', 200, 200, 'C');
                
                if (mode === '8ball') {
                    this.setup8Ball();
                } else if (mode === '9ball') {
                    this.setup9Ball();
                }
            }
            
            setup8Ball() {
                // Standard 8-ball rack
                const rackPositions = [
                    [600, 200], // 1 ball (front)
                    [620, 190], [620, 210], // 2nd row
                    [640, 180], [640, 200], [640, 220], // 3rd row
                    [660, 170], [660, 190], [660, 210], [660, 230], // 4th row
                    [680, 160], [680, 180], [680, 200], [680, 220], [680, 240] // 5th row
                ];
                
                // Ball arrangement: 1 in front, 8 in center, mix of solids and stripes
                const ballOrder = [1, 3, 2, 7, 8, 4, 12, 15, 9, 11, 6, 14, 5, 10, 13];
                
                for (let i = 0; i < 15; i++) {
                    const ballNumber = ballOrder[i];
                    this.createBall(`ball-${ballNumber}`, rackPositions[i][0], rackPositions[i][1], ballNumber.toString());
                }
            }
            
            setup9Ball() {
                // 9-ball diamond rack
                const rackPositions = [
                    [600, 200], // 1 ball (front)
                    [620, 190], [620, 210], // 2nd row
                    [640, 180], [640, 200], [640, 220], // 3rd row
                    [660, 190], [660, 210], // 4th row
                    [680, 200] // 9 ball (back)
                ];
                
                // Fixed positions: 1 in front, 9 in back, others random
                const ballOrder = [1, 3, 2, 7, 9, 4, 8, 6, 5];
                
                for (let i = 0; i < 9; i++) {
                    const ballNumber = ballOrder[i];
                    this.createBall(`ball-${ballNumber}`, rackPositions[i][0], rackPositions[i][1], ballNumber.toString());
                }
            }
            
            createBall(className, x, y, number) {
                const ball = document.createElement('div');
                ball.className = `ball ${className}`;
                ball.style.left = (x - 10) + 'px';
                ball.style.top = (y - 10) + 'px';
                ball.textContent = number;
                ball.dataset.number = number;
                ball.dataset.x = x;
                ball.dataset.y = y;
                
                this.table.appendChild(ball);
                this.balls.push({
                    element: ball,
                    x: x,
                    y: y,
                    number: number,
                    className: className,
                    potted: false
                });
                
                return ball;
            }
            
            handleTableClick(e) {
                if (!this.gameStarted || this.isCharging) return;
                
                const ball = e.target.closest('.ball');
                if (ball && ball.classList.contains('cue')) {
                    this.isAiming = true;
                    this.updateAimLine(e);
                }
            }
            
            handleMouseMove(e) {
                if (this.isAiming && !this.isCharging) {
                    this.updateAimLine(e);
                }
            }
            
            updateAimLine(e) {
                const rect = this.table.getBoundingClientRect();
                const cueBallRect = this.cueBall.getBoundingClientRect();
                const tableRect = this.table.getBoundingClientRect();
                
                const cueBallX = cueBallRect.left - tableRect.left + 10;
                const cueBallY = cueBallRect.top - tableRect.top + 10;
                const mouseX = e.clientX - tableRect.left;
                const mouseY = e.clientY - tableRect.top;
                
                const dx = mouseX - cueBallX;
                const dy = mouseY - cueBallY;
                const distance = Math.min(Math.sqrt(dx * dx + dy * dy), 100);
                const angle = Math.atan2(dy, dx);
                
                this.aimAngle = angle;
                
                this.aimLine.style.display = 'block';
                this.aimLine.style.left = cueBallX + 'px';
                this.aimLine.style.bottom = (400 - cueBallY) + 'px';
                this.aimLine.style.height = distance + 'px';
                this.aimLine.style.transform = `rotate(${angle * 180 / Math.PI + 90}deg)`;
            }
            
            handleMouseDown(e) {
                if (this.isAiming && !this.isCharging) {
                    this.isCharging = true;
                    this.chargePower();
                }
            }
            
            handleMouseUp(e) {
                if (this.isCharging) {
                    this.shoot();
                }
            }
            
            chargePower() {
                this.power = 0;
                const chargeInterval = setInterval(() => {
                    if (!this.isCharging) {
                        clearInterval(chargeInterval);
                        return;
                    }
                    
                    this.power = Math.min(this.power + 2, 100);
                    this.powerBar.style.width = this.power + '%';
                    
                    if (this.power >= 100) {
                        this.shoot();
                        clearInterval(chargeInterval);
                    }
                }, 50);
            }
            
            shoot() {
                if (!this.isCharging) return;
                
                this.isCharging = false;
                this.isAiming = false;
                this.aimLine.style.display = 'none';
                
                const force = this.power / 100;
                const dx = Math.cos(this.aimAngle) * force * 15;
                const dy = Math.sin(this.aimAngle) * force * 15;
                
                this.animateShot(dx, dy);
                
                this.power = 0;
                this.powerBar.style.width = '0%';
            }
            
            animateShot(dx, dy) {
                const cueBall = this.balls.find(b => b.className === 'cue');
                let vx = dx;
                let vy = dy;
                const friction = 0.98;
                const minSpeed = 0.1;
                
                const animate = () => {
                    // Update position
                    cueBall.x += vx;
                    cueBall.y += vy;
                    
                    // Bounce off walls
                    if (cueBall.x <= 10 || cueBall.x >= 790) {
                        vx = -vx * 0.8;
                        cueBall.x = Math.max(10, Math.min(790, cueBall.x));
                    }
                    if (cueBall.y <= 10 || cueBall.y >= 390) {
                        vy = -vy * 0.8;
                        cueBall.y = Math.max(10, Math.min(390, cueBall.y));
                    }
                    
                    // Check for collisions with other balls
                    this.checkCollisions(cueBall);
                    
                    // Check for pockets
                    this.checkPockets();
                    
                    // Apply friction
                    vx *= friction;
                    vy *= friction;
                    
                    // Update visual position
                    cueBall.element.style.left = (cueBall.x - 10) + 'px';
                    cueBall.element.style.top = (cueBall.y - 10) + 'px';
                    
                    // Continue animation if ball is still moving
                    if (Math.abs(vx) > minSpeed || Math.abs(vy) > minSpeed) {
                        requestAnimationFrame(animate);
                    } else {
                        this.endTurn();
                    }
                };
                
                animate();
            }
            
            checkCollisions(movingBall) {
                this.balls.forEach(ball => {
                    if (ball === movingBall || ball.potted) return;
                    
                    const dx = ball.x - movingBall.x;
                    const dy = ball.y - movingBall.y;
                    const distance = Math.sqrt(dx * dx + dy * dy);
                    
                    if (distance < 20) {
                        // Simple collision response
                        const angle = Math.atan2(dy, dx);
                        const targetX = movingBall.x + Math.cos(angle) * 20;
                        const targetY = movingBall.y + Math.sin(angle) * 20;
                        
                        ball.x = targetX;
                        ball.y = targetY;
                        ball.element.style.left = (ball.x - 10) + 'px';
                        ball.element.style.top = (ball.y - 10) + 'px';
                        
                        // Simple physics - transfer some momentum
                        setTimeout(() => {
                            this.animateBall(ball, Math.cos(angle) * 3, Math.sin(angle) * 3);
                        }, 10);
                    }
                });
            }
            
            animateBall(ball, vx, vy) {
                const friction = 0.95;
                const minSpeed = 0.1;
                
                const animate = () => {
                    ball.x += vx;
                    ball.y += vy;
                    
                    // Bounce off walls
                    if (ball.x <= 10 || ball.x >= 790) {
                        vx = -vx * 0.8;
                        ball.x = Math.max(10, Math.min(790, ball.x));
                    }
                    if (ball.y <= 10 || ball.y >= 390) {
                        vy = -vy * 0.8;
                        ball.y = Math.max(10, Math.min(390, ball.y));
                    }
                    
                    vx *= friction;
                    vy *= friction;
                    
                    ball.element.style.left = (ball.x - 10) + 'px';
                    ball.element.style.top = (ball.y - 10) + 'px';
                    
                    if (Math.abs(vx) > minSpeed || Math.abs(vy) > minSpeed) {
                        requestAnimationFrame(animate);
                    }
                };
                
                animate();
            }
            
            checkPockets() {
                const pockets = [
                    {x: 0, y: 0}, {x: 800, y: 0}, {x: 0, y: 400}, 
                    {x: 800, y: 400}, {x: 400, y: 0}, {x: 400, y: 400}
                ];
                
                this.balls.forEach(ball => {
                    if (ball.potted) return;
                    
                    pockets.forEach(pocket => {
                        const dx = ball.x - pocket.x;
                        const dy = ball.y - pocket.y;
                        const distance = Math.sqrt(dx * dx + dy * dy);
                        
                        if (distance < 25) {
                            this.pocketBall(ball);
                        }
                    });
                });
            }
            
            pocketBall(ball) {
                ball.potted = true;
                ball.element.style.display = 'none';
                
                if (ball.className === 'cue') {
                    // Cue ball potted - scratch
                    this.handleScratch();
                    return;
                }
                
                const ballNumber = parseInt(ball.number);
                this.handleBallPocketed(ballNumber);
            }
            
            handleScratch() {
                // Scratch - other player's turn, place cue ball back
                const cueBall = this.balls.find(b => b.className === 'cue');
                cueBall.x = 200;
                cueBall.y = 200;
                cueBall.potted = false;
                cueBall.element.style.display = 'block';
                cueBall.element.style.left = '190px';
                cueBall.element.style.top = '190px';
                
                this.switchPlayer();
            }
            
            handleBallPocketed(ballNumber) {
                if (this.gameMode === '8ball') {
                    this.handle8BallPocket(ballNumber);
                } else if (this.gameMode === '9ball') {
                    this.handle9BallPocket(ballNumber);
                }
            }
            
            handle8BallPocket(ballNumber) {
                if (ballNumber === 8) {
                    this.handleEightBallPocket();
                    return;
                }
                
                const isSolid = ballNumber <= 7;
                const isStripe = ballNumber >= 9;
                
                // Assign groups if not already assigned
                if (!this.player1Group && !this.player2Group) {
                    this.player1Group = isSolid ? 'solids' : 'stripes';
                    this.player2Group = isSolid ? 'stripes' : 'solids';
                }
                
                // Check if player pocketed their ball
                const currentPlayerGroup = this.currentPlayer === 1 ? this.player1Group : this.player2Group;
                const ballGroup = isSolid ? 'solids' : 'stripes';
                
                if (ballGroup === currentPlayerGroup) {
                    // Correct ball pocketed
                    this.pocketedBalls[`player${this.currentPlayer}`].push(ballNumber);
                    this.shotsRemaining++;
                } else {
                    // Wrong ball pocketed
                    this.switchPlayer();
                }
                
                this.updateUI();
                this.checkWinCondition();
            }
            
            handleEightBallPocket() {
                const currentPlayer = this.currentPlayer;
                const playerBalls = this.pocketedBalls[`player${currentPlayer}`];
                const requiredBalls = this.gameMode === '8ball' ? 7 : 0;
                
                if (playerBalls.length >= requiredBalls) {
                    this.declareWinner(currentPlayer);
                } else {
                    // Pocketed 8-ball too early
                    this.declareWinner(currentPlayer === 1 ? 2 : 1);
                }
            }
            
            handle9BallPocket(ballNumber) {
                if (ballNumber === 9) {
                    this.declareWinner(this.currentPlayer);
                } else {
                    this.pocketedBalls[`player${this.currentPlayer}`].push(ballNumber);
                    this.shotsRemaining++;
                    this.updateUI();
                }
            }
            
            endTurn() {
                this.shotsRemaining--;
                
                if (this.shotsRemaining <= 0) {
                    this.switchPlayer();
                }
                
                this.updateUI();
            }
            
            switchPlayer() {
                this.currentPlayer = this.currentPlayer === 1 ? 2 : 1;
                this.shotsRemaining = 1;
            }
            
            checkWinCondition() {
                if (this.gameMode === '8ball') {
                    const player1Balls = this.pocketedBalls.player1;
                    const player2Balls = this.pocketedBalls.player2;
                    
                    // Check if player has all their group balls
                    if (player1Balls.length >= 7) {
                        // Player 1 can shoot at 8-ball
                    }
                    if (player2Balls.length >= 7) {
                        // Player 2 can shoot at 8-ball
                    }
                }
            }
            
            declareWinner(player) {
                const winnerMessage = document.getElementById('winnerMessage');
                winnerMessage.textContent = `Player ${player} Wins!`;
                winnerMessage.style.display = 'block';
                this.gameStarted = false;
                
                setTimeout(() => {
                    winnerMessage.style.display = 'none';
                }, 3000);
            }
            
            updateUI() {
                document.getElementById('currentPlayer').textContent = `Player ${this.currentPlayer}'s Turn`;
                document.getElementById('shotsRemaining').textContent = this.shotsRemaining;
                
                // Update group assignments
                document.getElementById('player1Group').textContent = this.player1Group || '-';
                document.getElementById('player2Group').textContent = this.player2Group || '-';
                
                // Update pocketed balls display
                this.updatePocketedBallsDisplay('player1');
                this.updatePocketedBallsDisplay('player2');
                
                // Highlight current player
                document.getElementById('player1Info').classList.toggle('current-player', this.currentPlayer === 1);
                document.getElementById('player2Info').classList.toggle('current-player', this.currentPlayer === 2);
            }
            
            updatePocketedBallsDisplay(player) {
                const container = document.getElementById(`${player}Balls`);
                container.innerHTML = '';
                
                this.pocketedBalls[player].forEach(ballNumber => {
                    const ball = document.createElement('div');
                    ball.className = `pocketed-ball ball-${ballNumber}`;
                    ball.textContent = ballNumber;
                    
                    // Apply the same color scheme as the main balls
                    const ballElement = document.querySelector(`.ball-${ballNumber}`);
                    if (ballElement) {
                        const styles = window.getComputedStyle(ballElement);
                        ball.style.background = styles.background;
                        ball.style.color = styles.color;
                    }
                    
                    container.appendChild(ball);
                });
            }
            
            resetGame() {
                this.gameMode = null;
                this.gameStarted = false;
                this.isAiming = false;
                this.isCharging = false;
                this.power = 0;
                this.balls = [];
                this.pocketedBalls = { player1: [], player2: [] };
                this.player1Group = null;
                this.player2Group = null;
                this.currentPlayer = 1;
                this.shotsRemaining = 1;
                
                document.getElementById('gameMode').textContent = 'Select a game mode';
                document.getElementById('gameInfo').style.display = 'none';
                this.powerBar.style.width = '0%';
                this.aimLine.style.display = 'none';
                
                // Clear the table
                this.table.innerHTML = `
                    <div class="pocket corner-top-left"></div>
                    <div class="pocket corner-top-right"></div>
                    <div class="pocket corner-bottom-left"></div>
                    <div class="pocket corner-bottom-right"></div>
                    <div class="pocket side-top"></div>
                    <div class="pocket side-bottom"></div>
                    <div class="aim-line" id="aimLine"></div>
                `;
                this.aimLine = document.getElementById('aimLine');
            }
        }
        
        // Global functions for buttons
        function startGame(mode) {
            game.startGame(mode);
        }
        
        function resetGame() {
            game.resetGame();
        }
        
        // Initialize the game
        const game = new PoolGame();
    </script>
</body>
</html>