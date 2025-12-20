@extends('layouts.main')

@push('scripts')
    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('data', () => ({
                svaip: {
                    name: '',
                    cards: [],
                    description: '',
                },
                selectedCard: null,
                showTips: false,
                canvas: null,
                ctx: null,
                connecting: false,
                connectFrom: null,
                connectSide: null,
                
                // Preview mode
                showPreview: false,
                previewCardIndex: 0,
                previewAnswers: [],
                previewComplete: false,
                // Swipe state
                previewOffsetX: 0,
                previewOffsetY: 0,
                previewRotation: 0,
                previewStartX: null,
                previewStartY: null,
                previewLeaning: -1,
                previewSensitivity: 20,

                init() {
                    this.addCard();
                    this.$nextTick(() => {
                        this.canvas = this.$refs.canvas;
                        if (this.canvas) {
                            this.ctx = this.canvas.getContext('2d');
                            this.resizeCanvas();
                            window.addEventListener('resize', () => this.resizeCanvas());
                        }
                    });
                },

                resizeCanvas() {
                    if (!this.canvas) return;
                    const container = this.canvas.parentElement;
                    this.canvas.width = Math.max(container.scrollWidth, 1200);
                    this.canvas.height = Math.max(container.scrollHeight, 600);
                    this.drawConnections();
                },

                addCard() {
                    const newCard = {
                        type: 'question',
                        question: '',
                        description: '',
                        options: ['Yes', 'No'],
                        branches: {0: null, 1: null},
                        x: 100 + (this.svaip.cards.length * 50),
                        y: 100 + (this.svaip.cards.length * 30),
                    };
                    this.svaip.cards.push(newCard);
                    this.selectedCard = this.svaip.cards.length - 1;
                },

                addEndCard() {
                    const newCard = {
                        type: 'end',
                        message: 'Thank you for completing the svaip!',
                        formFields: [],
                        x: 100 + (this.svaip.cards.length * 50),
                        y: 100 + (this.svaip.cards.length * 30),
                    };
                    this.svaip.cards.push(newCard);
                    this.selectedCard = this.svaip.cards.length - 1;
                },

                addFormField() {
                    if (this.selectedCard === null) return;
                    const card = this.svaip.cards[this.selectedCard];
                    if (card.type !== 'end') return;
                    
                    card.formFields.push({
                        label: '',
                        type: 'text',
                        required: false
                    });
                },

                removeFormField(fieldIndex) {
                    if (this.selectedCard === null) return;
                    const card = this.svaip.cards[this.selectedCard];
                    if (card.type !== 'end') return;
                    
                    card.formFields.splice(fieldIndex, 1);
                },

                removeCard(index) {
                    if (this.svaip.cards.length == 1) return;
                    
                    // Remove branches pointing to this card
                    const cardIndex = index + 1;
                    this.svaip.cards.forEach(card => {
                        if (card.type !== 'end') {
                            if (card.branches[0] === cardIndex) card.branches[0] = null;
                            if (card.branches[1] === cardIndex) card.branches[1] = null;
                        }
                    });

                    this.svaip.cards.splice(index, 1);
                    if (this.selectedCard === index) this.selectedCard = null;
                    if (this.selectedCard > index) this.selectedCard--;
                    this.$nextTick(() => this.drawConnections());
                },

                startConnection(index, side) {
                    // Cancel if clicking the same button that started the connection
                    if (this.connecting && this.connectFrom === index && this.connectSide === side) {
                        this.connecting = false;
                        this.connectFrom = null;
                        this.connectSide = null;
                        return;
                    }
                    this.connecting = true;
                    this.connectFrom = index;
                    this.connectSide = side;
                },

                finishConnection(index) {
                    if (this.connecting && this.connectFrom !== null && this.connectFrom !== index) {
                        this.svaip.cards[this.connectFrom].branches[this.connectSide] = index + 1;
                        this.$nextTick(() => this.drawConnections());
                    }
                    this.connecting = false;
                    this.connectFrom = null;
                    this.connectSide = null;
                },

                removeConnection(cardIndex, side) {
                    this.svaip.cards[cardIndex].branches[side] = null;
                    this.$nextTick(() => this.drawConnections());
                },

                drawConnections() {
                    if (!this.ctx || !this.canvas) return;

                    this.ctx.clearRect(0, 0, this.canvas.width, this.canvas.height);

                    this.svaip.cards.forEach((card, fromIndex) => {
                        // Skip End Cards - they don't have branches
                        if (card.type === 'end') return;
                        
                        [0, 1].forEach(side => {
                            const targetIndex = card.branches[side];
                            if (targetIndex !== null) {
                                const toIndex = targetIndex - 1;
                                if (toIndex >= 0 && toIndex < this.svaip.cards.length) {
                                    this.drawArrow(fromIndex, toIndex, side);
                                }
                            }
                        });
                    });
                },

                drawArrow(fromIndex, toIndex, side) {
                    const fromCard = this.svaip.cards[fromIndex];
                    const toCard = this.svaip.cards[toIndex];
                    
                    const startX = fromCard.x + (side === 0 ? 30 : 170);
                    const startY = fromCard.y + 150;
                    const endX = toCard.x + 100;
                    const endY = toCard.y;

                    this.ctx.strokeStyle = side === 0 ? '#6366f1' : '#f59e0b';
                    this.ctx.lineWidth = 3;
                    this.ctx.setLineDash([5, 5]);
                    
                    this.ctx.beginPath();
                    this.ctx.moveTo(startX, startY);
                    
                    // Calculate control points for smooth curve
                    const controlY1 = startY + Math.abs(endY - startY) * 0.5;
                    const controlY2 = endY - Math.abs(endY - startY) * 0.5;
                    
                    this.ctx.bezierCurveTo(
                        startX, controlY1,
                        endX, controlY2,
                        endX, endY
                    );
                    this.ctx.stroke();
                },

                // Preview methods
                startPreview() {
                    if (this.svaip.cards.length === 0 || !this.svaip.cards[0].question) {
                        alert('Please add at least one card with a question before previewing.');
                        return;
                    }
                    this.previewCardIndex = 0;
                    this.previewAnswers = [];
                    this.previewComplete = false;
                    this.previewOffsetX = 0;
                    this.previewOffsetY = 0;
                    this.previewRotation = 0;
                    this.previewLeaning = -1;
                    this.showPreview = true;
                },

                previewStartDrag(e) {
                    this.previewStartX = e.clientX;
                    this.previewStartY = e.clientY;
                    e.target.setPointerCapture(e.pointerId);
                },

                previewDrag(e) {
                    if (this.previewStartX === null) return;
                    
                    const moveX = e.clientX - this.previewStartX;

                    // Horizontal swipe only
                    if (moveX > 0) {
                        this.previewLeaning = 1; // Right
                    } else if (moveX < 0) {
                        this.previewLeaning = 0; // Left
                    } else {
                        this.previewLeaning = -1;
                    }
                    const limit = window.innerWidth < 640 ? 25 : 40;
                    this.previewOffsetX = Math.max(Math.min(moveX, limit), -limit);
                    this.previewRotation = this.previewOffsetX / this.previewSensitivity;
                },

                previewEndDrag(e) {
                    e?.target?.releasePointerCapture?.(e.pointerId);

                    if (this.previewLeaning !== -1) {
                        const card = this.svaip.cards[this.previewCardIndex];
                        
                        // Left (0) or Right (1)
                        this.previewAnswers.push({
                            cardIndex: this.previewCardIndex,
                            question: card.question,
                            answer: card.options[this.previewLeaning],
                            side: this.previewLeaning
                        });

                        // Check for branch or go to next card
                        const branchTarget = card.branches[this.previewLeaning];
                        
                        if (branchTarget !== null && branchTarget !== undefined && branchTarget > 0) {
                            this.previewCardIndex = branchTarget - 1;
                        } else {
                            this.previewCardIndex++;
                        }

                        // Check if we've reached the end or invalid state
                        if (this.previewCardIndex >= this.svaip.cards.length || this.previewCardIndex < 0) {
                            this.previewComplete = true;
                        }
                    }

                    this.previewOffsetX = 0;
                    this.previewOffsetY = 0;
                    this.previewRotation = 0;
                    this.previewStartX = null;
                    this.previewStartY = null;
                    this.previewLeaning = -1;
                },

                restartPreview() {
                    this.previewCardIndex = 0;
                    this.previewAnswers = [];
                    this.previewComplete = false;
                    this.previewOffsetX = 0;
                    this.previewOffsetY = 0;
                    this.previewRotation = 0;
                    this.previewLeaning = -1;
                },

                closePreview() {
                    this.showPreview = false;
                },

                cancel() {
                    const answer = confirm('Are you sure you want to cancel? All unsaved data will be lost');
                    if (!answer) return;
                    window.location.href = "{{ route('flow.index') }}";
                },
            }));
        });
    </script>
@endpush

@section('content')
    <div class="px-4 sm:px-6 lg:px-8">
        <form x-data="data()" action="{{ route('flow.store') }}" method="POST">
            @csrf
            
            <!-- Collapsible Tips -->
            <div class="mb-4">
                <button type="button" @click="showTips = !showTips" 
                    class="flex items-center gap-2 text-sm text-gray-600 hover:text-gray-900">
                    <i class="fa-regular fa-lightbulb text-yellow-500"></i>
                    <span>Tips for creating effective svaips</span>
                    <i :class="showTips ? 'fa-chevron-up' : 'fa-chevron-down'" class="fa-solid text-xs"></i>
                </button>
                <div x-show="showTips" x-collapse class="mt-2 bg-yellow-50 border border-yellow-200 rounded-lg p-4 text-sm text-gray-700">
                    <p class="mb-2"><strong>1.</strong> Give your svaip a descriptive name so users can easily identify its purpose.</p>
                    <p class="mb-2"><strong>2.</strong> Formulate each question as precise and clear as possible. The description can provide additional context.</p>
                    <p><strong>3.</strong> Questions should be binary — answerable with two distinct options (Yes/No, Accept/Reject, Like/Dislike, etc.).</p>
                </div>
            </div>

            <!-- Basic Info -->
            <div class="bg-white p-6 rounded-lg shadow-lg mb-4">
                <div class="mb-4">
                    <label for="name" class="block text-sm font-medium text-gray-700 mb-1">Name</label>
                    <input x-model="svaip.name" id="name" name="name" autocomplete="off" type="text" required
                        class="appearance-none rounded-md relative block w-full px-3 py-2 border border-gray-300 placeholder-gray-500 text-gray-900 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                        placeholder="Descriptive name for your svaip">
                </div>
                <div>
                    <label for="description" class="block text-sm font-medium text-gray-700 mb-1">Description (optional)</label>
                    <input x-model="svaip.description" id="description" name="description" autocomplete="off" type="text"
                        class="appearance-none rounded-md relative block w-full px-3 py-2 border border-gray-300 placeholder-gray-500 text-gray-900 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                        placeholder="Description of your svaip">
                </div>
            </div>

            <!-- Flow Designer -->
            <div class="bg-white rounded-lg shadow-lg p-4">
                <div class="mb-4 flex justify-between items-center">
                    <h3 class="text-lg font-semibold text-gray-900">Flow Designer</h3>
                    <div class="flex gap-2">
                        <button type="button" @click="addCard()"
                            class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 text-sm">
                            <i class="fa-solid fa-plus mr-2"></i>Add Card
                        </button>
                        <button type="button" @click="addEndCard()"
                            class="px-4 py-2 bg-emerald-600 text-white rounded-md hover:bg-emerald-700 text-sm">
                            <i class="fa-solid fa-flag-checkered mr-2"></i>Add End Card
                        </button>
                    </div>
                </div>

                <!-- Card Editor Panel (above canvas) -->
                <div x-show="selectedCard !== null" class="mb-4 bg-gray-50 rounded-lg p-4 border border-gray-200 overflow-y-auto" style="height: 300px;">
                    <template x-if="selectedCard !== null && svaip.cards[selectedCard]">
                        <div>
                            <div class="flex justify-between items-center mb-3">
                                <h4 class="text-md font-semibold text-gray-900">
                                    <span x-show="svaip.cards[selectedCard].type === 'end'" class="text-emerald-600">
                                        <i class="fa-solid fa-flag-checkered mr-1"></i>End Card
                                    </span>
                                    <span x-show="svaip.cards[selectedCard].type !== 'end'">
                                        Edit Card #<span x-text="selectedCard + 1"></span>
                                    </span>
                                </h4>
                                <button type="button" @click="selectedCard = null" class="text-gray-400 hover:text-gray-600">
                                    <i class="fa-solid fa-times"></i>
                                </button>
                            </div>
                            
                            <!-- Question Card Editor -->
                            <template x-if="svaip.cards[selectedCard] && svaip.cards[selectedCard].type !== 'end'">
                                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                                    <div class="md:col-span-2">
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Question</label>
                                        <input x-model="svaip.cards[selectedCard].question" type="text" 
                                            :name="`cards[${selectedCard}][question]`" required
                                            class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                                            placeholder="A precise question to ask the user">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Left swipe</label>
                                        <input x-model="svaip.cards[selectedCard].options[0]" 
                                            :name="`cards[${selectedCard}][options][0]`" type="text" required
                                            @input="$nextTick(() => drawConnections())"
                                            class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Right swipe</label>
                                        <input x-model="svaip.cards[selectedCard].options[1]" 
                                            :name="`cards[${selectedCard}][options][1]`" type="text" required
                                            @input="$nextTick(() => drawConnections())"
                                            class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                                    </div>
                                    <div class="md:col-span-4">
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Description (optional)</label>
                                        <input x-model="svaip.cards[selectedCard].description" type="text" 
                                            :name="`cards[${selectedCard}][description]`"
                                            class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                                            placeholder="Additional context">
                                    </div>
                                </div>
                            </template>
                            
                            <!-- End Card Editor -->
                            <template x-if="svaip.cards[selectedCard] && svaip.cards[selectedCard].type === 'end'">
                                <div>
                                    <div class="mb-4">
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Completion Message</label>
                                        <textarea x-model="svaip.cards[selectedCard].message" rows="2"
                                            class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-emerald-500 focus:border-emerald-500 sm:text-sm"
                                            placeholder="Message shown when user reaches this end point"></textarea>
                                    </div>
                                    
                                    <div class="border-t pt-4">
                                        <div class="flex justify-between items-center mb-3">
                                            <label class="block text-sm font-medium text-gray-700">Form Fields (optional)</label>
                                            <button type="button" @click="addFormField()"
                                                class="px-3 py-1 bg-emerald-100 text-emerald-700 rounded-md hover:bg-emerald-200 text-sm">
                                                <i class="fa-solid fa-plus mr-1"></i>Add Field
                                            </button>
                                        </div>
                                        
                                        <div x-show="!svaip.cards[selectedCard].formFields || svaip.cards[selectedCard].formFields.length === 0" 
                                            class="text-sm text-gray-500 italic">
                                            No form fields. Add fields to collect additional information from users.
                                        </div>
                                        
                                        <template x-for="(field, fieldIndex) in (svaip.cards[selectedCard].formFields || [])" :key="fieldIndex">
                                            <div class="flex gap-2 mb-2 items-start bg-white p-2 rounded border">
                                                <div class="flex-1">
                                                    <input x-model="field.label" type="text" placeholder="Field label"
                                                        class="block w-full px-2 py-1 border border-gray-300 rounded text-sm">
                                                </div>
                                                <div class="w-32">
                                                    <select x-model="field.type"
                                                        class="block w-full px-2 py-1 border border-gray-300 rounded text-sm">
                                                        <option value="text">Text</option>
                                                        <option value="email">Email</option>
                                                        <option value="number">Number</option>
                                                        <option value="textarea">Long text</option>
                                                    </select>
                                                </div>
                                                <div class="flex items-center gap-1">
                                                    <input type="checkbox" x-model="field.required" :id="`req-${fieldIndex}`" class="rounded">
                                                    <label :for="`req-${fieldIndex}`" class="text-xs text-gray-600">Required</label>
                                                </div>
                                                <button type="button" @click="removeFormField(fieldIndex)" 
                                                    class="text-red-500 hover:text-red-700 px-2 py-1">
                                                    <i class="fa-solid fa-trash text-xs"></i>
                                                </button>
                                            </div>
                                        </template>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </template>
                </div>
                
                <div class="relative bg-gray-50 rounded-lg border-2 border-gray-200" style="height: 800px; overflow: auto;" x-ref="scrollContainer">
                    <canvas x-ref="canvas" class="absolute top-0 left-0 pointer-events-none" style="z-index: 1;" width="1200" height="800"></canvas>
                    
                    <div style="position: relative; z-index: 2; min-height: 800px; min-width: 1200px;">
                        <template x-for="(card, index) in svaip.cards" :key="index">
                            <div class="absolute bg-white rounded-[0.6rem] shadow-md border-2 cursor-move overflow-hidden"
                                :style="`left: ${card.x}px; top: ${card.y}px; width: 200px;`"
                                :class="selectedCard === index ? (card.type === 'end' ? 'border-emerald-500' : 'border-indigo-500') : 'border-gray-300'"
                                @mousedown.prevent="selectedCard = index"
                                x-init="
                                    let isDragging = false;
                                    let offsetX, offsetY;
                                    $el.addEventListener('mousedown', (e) => {
                                        if (e.target.tagName === 'BUTTON' || e.target.tagName === 'I') return;
                                        isDragging = true;
                                        const rect = $refs.scrollContainer.getBoundingClientRect();
                                        offsetX = e.clientX - rect.left + $refs.scrollContainer.scrollLeft - card.x;
                                        offsetY = e.clientY - rect.top + $refs.scrollContainer.scrollTop - card.y;
                                    });
                                    document.addEventListener('mousemove', (e) => {
                                        if (isDragging) {
                                            const rect = $refs.scrollContainer.getBoundingClientRect();
                                            card.x = e.clientX - rect.left + $refs.scrollContainer.scrollLeft - offsetX;
                                            card.y = e.clientY - rect.top + $refs.scrollContainer.scrollTop - offsetY;
                                            drawConnections();
                                        }
                                    });
                                    document.addEventListener('mouseup', () => {
                                        isDragging = false;
                                    });
                                ">
                                <!-- Question Card Header -->
                                <div x-show="card.type !== 'end'" class="p-3 bg-gradient-to-r from-indigo-500 to-purple-500 text-white flex justify-between items-center">
                                    <span class="font-semibold text-sm">Card #<span x-text="index + 1"></span></span>
                                    <button type="button" @click.stop="removeCard(index)" @mousedown.stop class="text-white hover:text-red-200">
                                        <i class="fa-solid fa-times"></i>
                                    </button>
                                </div>
                                <!-- End Card Header -->
                                <div x-show="card.type === 'end'" class="p-3 bg-gradient-to-r from-emerald-500 to-teal-500 text-white flex justify-between items-center">
                                    <span class="font-semibold text-sm"><i class="fa-solid fa-flag-checkered mr-1"></i>End Card</span>
                                    <button type="button" @click.stop="removeCard(index)" @mousedown.stop class="text-white hover:text-red-200">
                                        <i class="fa-solid fa-times"></i>
                                    </button>
                                </div>
                                
                                <!-- Question Card Body -->
                                <template x-if="card.type !== 'end'">
                                    <div class="p-3">
                                        <div class="text-xs font-medium text-gray-700 mb-1">Question:</div>
                                        <div class="text-sm text-gray-900 mb-2 line-clamp-2" x-text="card.question || ''"></div>
                                        
                                        <div class="flex gap-2 mt-3">
                                            <div class="flex-1">
                                                <div class="text-xs text-gray-500 mb-1" x-text="card.options[0]"></div>
                                                <button type="button" 
                                                    @click.stop="card.branches[0] !== null ? removeConnection(index, 0) : startConnection(index, 0)"
                                                    @mousedown.stop
                                                    :class="card.branches[0] !== null ? 'bg-indigo-500 hover:bg-indigo-600' : connecting && connectFrom === index && connectSide === 0 ? 'bg-indigo-400 ring-2 ring-indigo-300' : 'bg-indigo-100 hover:bg-indigo-200'"
                                                    class="w-full text-xs py-1 px-2 rounded text-white">
                                                    <i :class="card.branches[0] !== null ? 'fa-link-slash' : connecting && connectFrom === index && connectSide === 0 ? 'fa-times' : 'fa-link'" class="fa-solid"></i>
                                                </button>
                                            </div>
                                            <div class="flex-1">
                                                <div class="text-xs text-gray-500 mb-1" x-text="card.options[1]"></div>
                                                <button type="button"
                                                    @click.stop="card.branches[1] !== null ? removeConnection(index, 1) : startConnection(index, 1)"
                                                    @mousedown.stop
                                                    :class="card.branches[1] !== null ? 'bg-amber-500 hover:bg-amber-600' : connecting && connectFrom === index && connectSide === 1 ? 'bg-amber-400 ring-2 ring-amber-300' : 'bg-amber-100 hover:bg-amber-200'"
                                                    class="w-full text-xs py-1 px-2 rounded text-white">
                                                    <i :class="card.branches[1] !== null ? 'fa-link-slash' : connecting && connectFrom === index && connectSide === 1 ? 'fa-times' : 'fa-link'" class="fa-solid"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </template>
                                
                                <!-- End Card Body -->
                                <template x-if="card.type === 'end'">
                                    <div class="p-3">
                                        <div class="text-sm text-gray-900 mb-2 line-clamp-3" x-text="card.message || ''"></div>
                                        <div x-show="card.formFields && card.formFields.length > 0" class="text-xs text-emerald-600 mt-2">
                                            <i class="fa-solid fa-list-check mr-1"></i><span x-text="card.formFields.length"></span> form field(s)
                                        </div>
                                    </div>
                                </template>
                                
                                <div x-show="connecting && connectFrom !== index" 
                                    @click.stop="finishConnection(index)"
                                    @mousedown.stop
                                    class="absolute inset-0 bg-indigo-500 bg-opacity-20 rounded-lg flex items-center justify-center cursor-pointer border-2 border-indigo-500 border-dashed">
                                    <span class="text-indigo-700 font-semibold">Click to connect</span>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="mt-6 flex gap-4">
                <button type="button" @click="startPreview()"
                    class="flex-1 flex justify-center py-2 px-4 border-2 border-indigo-600 text-sm font-medium rounded-md text-indigo-600 bg-white hover:bg-indigo-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                    <i class="fa-solid fa-play mr-2"></i>Preview Flow
                </button>
                <button type="submit"
                    class="flex-1 flex justify-center py-2 px-4 border border-transparent text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                    <i class="fa-solid fa-save mr-2"></i>Save Svaip
                </button>
            </div>
            <div class="mt-2">
                <button type="button" x-on:click="cancel()"
                    class="w-full flex justify-center py-2 px-4 border border-transparent text-sm font-medium rounded-md text-gray-600 bg-gray-100 hover:bg-gray-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500">
                    Discard Svaip
                </button>
            </div>

            @include('flow.preview')
        </form>
    </div>
@endsection
