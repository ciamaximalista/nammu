// Nammu admin — itinerarios: clase personalizada, modal de estadísticas y editor de cuestionarios.
        document.addEventListener('DOMContentLoaded', function() {
            var classSelect = document.querySelector('[data-itinerary-class-select]');
            var customWrapper = document.querySelector('[data-itinerary-class-custom-wrapper]');
            var customInput = document.getElementById('itinerary_class_custom');
            if (!classSelect || !customWrapper) {
                return;
            }
            var toggleCustomField = function() {
                if (classSelect.value === 'Otros') {
                    customWrapper.classList.remove('d-none');
                    if (customInput) {
                        customInput.setAttribute('required', 'required');
                    }
                } else {
                    customWrapper.classList.add('d-none');
                    if (customInput) {
                        customInput.removeAttribute('required');
                    }
                }
            };
            classSelect.addEventListener('change', toggleCustomField);
            toggleCustomField();
        });

        $(function() {
            var statsModal = $('#itineraryStatsModal');
            if (!statsModal.length) {
                return;
            }
            statsModal.on('show.bs.modal', function (event) {
                var button = $(event.relatedTarget);
                var statsRaw = button && button.attr('data-itinerary-stats') ? button.attr('data-itinerary-stats') : '{}';
                var title = button && button.data('itinerary-title') ? button.data('itinerary-title') : 'Itinerario';
                var slug = button && button.data('itinerary-slug') ? button.data('itinerary-slug') : '';
                var stats;
                try {
                    stats = JSON.parse(statsRaw);
                } catch (err) {
                    stats = {started: 0, topics: []};
                }
                stats = stats || {};
                var started = parseInt(stats.started, 10);
                if (isNaN(started) || started < 0) {
                    started = 0;
                }
                statsModal.find('[data-stats-title]').text(title);
                var presentationReaders = parseInt(stats.presentation_readers, 10);
                if (isNaN(presentationReaders) || presentationReaders < 0) {
                    presentationReaders = started;
                }
                statsModal.find('[data-stats-started]').text('Leyeron la presentación del itinerario ' + presentationReaders + ' usuarios reales');
                var note = statsModal.find('[data-stats-note]');
                if (note.length) {
                    note.text('Puedes poner a cero todas las métricas de "' + title + '". Esta acción no se puede deshacer.');
                }
                var resetForm = statsModal.find('[data-reset-stats-form]');
                var resetSlugInput = statsModal.find('[data-reset-stats-slug]');
                var resetBtn = statsModal.find('[data-reset-stats-button]');
                if (resetSlugInput.length) {
                    resetSlugInput.val(slug || '');
                }
                if (resetBtn.length) {
                    resetBtn.prop('disabled', !slug);
                }
                var tbody = statsModal.find('[data-stats-table-body]');
                tbody.empty();
                var topics = Array.isArray(stats.topics) ? stats.topics : [];
                if (!topics.length) {
                    tbody.append('<tr><td colspan="3" class="text-muted">Todavía no hay usuarios con progreso registrado.</td></tr>');
                    return;
                }
                topics.forEach(function(topic) {
                    var number = parseInt(topic.number, 10);
                    if (isNaN(number)) {
                        number = 0;
                    }
                    var label = number > 0 ? 'Tema ' + number : 'Tema';
                    if (topic.title) {
                        label += ' — ' + topic.title;
                    }
                    var count = parseInt(topic.count, 10);
                    if (isNaN(count) || count < 0) {
                        count = 0;
                    }
                    var percentLabel = started > 0 ? ((count / started) * 100).toFixed(1) + '%' : '—';
                    var row = $('<tr></tr>');
                    row.append($('<td></td>').text(label));
                    row.append($('<td></td>').text(count));
                row.append($('<td></td>').text(percentLabel));
                tbody.append(row);
            });
            statsModal.find('[data-reset-stats-form]').on('submit', function() {
                return window.confirm('¿Seguro que quieres poner a cero las estadísticas de este itinerario? Esta acción eliminará todos los conteos registrados.');
            });
        });
        });

        document.addEventListener('DOMContentLoaded', function() {
            var quizModal = document.querySelector('[data-topic-quiz-modal]');
            var quizBackdrop = document.querySelector('[data-topic-quiz-backdrop]');
            var triggers = document.querySelectorAll('[data-quiz-trigger]');
            if (!quizModal || !quizBackdrop || !triggers.length) {
                return;
            }
            var minInput = quizModal.querySelector('[data-topic-quiz-min]');
            var questionsWrapper = quizModal.querySelector('[data-topic-quiz-questions]');
            var addQuestionBtn = quizModal.querySelector('[data-topic-quiz-add-question]');
            var saveBtn = quizModal.querySelector('[data-topic-quiz-save]');
            var clearBtn = quizModal.querySelector('[data-topic-quiz-clear]');
            var closeButtons = quizModal.querySelectorAll('[data-topic-quiz-close]');
            var modalTitle = quizModal.querySelector('#topicQuizModalTitle');
            var activeContext = {
                input: null,
                summary: null,
                trigger: null
            };

            function parseQuizValue(value) {
                var payload = (value || '').trim();
                if (payload === '') {
                    return {minimum_correct: 1, questions: []};
                }
                try {
                    var parsed = JSON.parse(payload);
                    if (parsed && Array.isArray(parsed.questions)) {
                        var min = parseInt(parsed.minimum_correct, 10);
                        if (!min || min < 1) {
                            min = parsed.questions.length || 1;
                        }
                        return {
                            minimum_correct: Math.min(parsed.questions.length || 1, min),
                            questions: parsed.questions
                        };
                    }
                } catch (err) {
                    console.warn('No se pudo leer la autoevaluación almacenada', err);
                }
                return {minimum_correct: 1, questions: []};
            }

            function updateSummary(input, summaryEl, trigger) {
                if (!input) {
                    return;
                }
                var state = parseQuizValue(input.value);
                if (summaryEl) {
                    if (!state.questions.length) {
                        summaryEl.textContent = '';
                    } else {
                        var label = state.questions.length === 1 ? 'pregunta' : 'preguntas';
                        summaryEl.textContent = state.questions.length + ' ' + label + ' · mínimo ' + state.minimum_correct + ' correctas';
                    }
                }
                if (trigger) {
                    trigger.textContent = state.questions.length ? 'Editar autoevaluación' : 'Añadir autoevaluación';
                }
            }

            function toggleModal(show) {
                if (show) {
                    quizModal.classList.remove('d-none');
                    quizBackdrop.classList.remove('d-none');
                    quizModal.setAttribute('aria-hidden', 'false');
                } else {
                    quizModal.classList.add('d-none');
                    quizBackdrop.classList.add('d-none');
                    quizModal.setAttribute('aria-hidden', 'true');
                }
            }

            function addAnswer(container, answerData) {
                var row = document.createElement('div');
                row.className = 'topic-quiz-answer';
                row.setAttribute('data-quiz-answer', '1');

                var checkbox = document.createElement('input');
                checkbox.type = 'checkbox';
                checkbox.className = 'mr-2';
                checkbox.checked = !!(answerData && answerData.correct);

                var input = document.createElement('input');
                input.type = 'text';
                input.className = 'form-control';
                input.placeholder = 'Respuesta posible';
                input.value = answerData && answerData.text ? answerData.text : '';
                input.setAttribute('data-quiz-answer-text', '1');

                var removeBtn = document.createElement('button');
                removeBtn.type = 'button';
                removeBtn.className = 'btn btn-link text-danger btn-sm';
                removeBtn.textContent = 'Quitar';
                removeBtn.addEventListener('click', function() {
                    row.remove();
                });

                row.appendChild(checkbox);
                row.appendChild(input);
                row.appendChild(removeBtn);
                container.appendChild(row);
            }

            function addQuestion(questionData) {
                var block = document.createElement('div');
                block.className = 'topic-quiz-question';
                block.setAttribute('data-quiz-question', '1');

                var header = document.createElement('div');
                header.className = 'd-flex justify-content-between align-items-center mb-2';

                var title = document.createElement('h5');
                title.className = 'mb-0';
                title.textContent = 'Pregunta';
                header.appendChild(title);

                var removeBtn = document.createElement('button');
                removeBtn.type = 'button';
                removeBtn.className = 'btn btn-link text-danger btn-sm';
                removeBtn.textContent = 'Quitar';
                removeBtn.addEventListener('click', function() {
                    block.remove();
                });
                header.appendChild(removeBtn);

                var questionInput = document.createElement('input');
                questionInput.type = 'text';
                questionInput.className = 'form-control mb-3';
                questionInput.placeholder = 'Enunciado de la pregunta';
                questionInput.value = questionData && questionData.text ? questionData.text : '';
                questionInput.setAttribute('data-quiz-question-text', '1');

                var answersWrapper = document.createElement('div');
                answersWrapper.className = 'topic-quiz-answers';
                answersWrapper.setAttribute('data-quiz-answers', '1');

                var answers = questionData && Array.isArray(questionData.answers) ? questionData.answers : [];
                if (!answers.length) {
                    addAnswer(answersWrapper);
                    addAnswer(answersWrapper);
                } else {
                    answers.forEach(function(answer) {
                        addAnswer(answersWrapper, answer);
                    });
                }

                var addAnswerBtn = document.createElement('button');
                addAnswerBtn.type = 'button';
                addAnswerBtn.className = 'btn btn-outline-primary btn-sm mt-2';
                addAnswerBtn.textContent = 'Añadir respuesta';
                addAnswerBtn.addEventListener('click', function() {
                    addAnswer(answersWrapper);
                });

                block.appendChild(header);
                block.appendChild(questionInput);
                block.appendChild(answersWrapper);
                block.appendChild(addAnswerBtn);
                questionsWrapper.appendChild(block);
            }

            function loadQuestionsIntoModal(state) {
                questionsWrapper.innerHTML = '';
                var quizState = state || {minimum_correct: 1, questions: []};
                var questions = quizState.questions.length ? quizState.questions : [];
                minInput.value = quizState.minimum_correct || 1;
                if (!questions.length) {
                    addQuestion();
                    return;
                }
                questions.forEach(function(question) {
                    addQuestion(question);
                });
            }

            function collectQuizData() {
                var questionBlocks = questionsWrapper.querySelectorAll('[data-quiz-question]');
                var quizQuestions = [];
                var hasError = false;
                questionBlocks.forEach(function(block) {
                    if (hasError) {
                        return;
                    }
                    var textInput = block.querySelector('[data-quiz-question-text]');
                    var questionText = textInput ? textInput.value.trim() : '';
                    if (questionText === '') {
                        hasError = true;
                        alert('Todas las preguntas necesitan un enunciado.');
                        return;
                    }
                    var answers = [];
                    var answerNodes = block.querySelectorAll('[data-quiz-answer]');
                    answerNodes.forEach(function(answerNode) {
                        var answerTextInput = answerNode.querySelector('[data-quiz-answer-text]');
                        var answerText = answerTextInput ? answerTextInput.value.trim() : '';
                        if (answerText === '') {
                            return;
                        }
                        var checkbox = answerNode.querySelector('input[type="checkbox"]');
                        answers.push({
                            text: answerText,
                            correct: checkbox ? checkbox.checked : false
                        });
                    });
                    if (!answers.length) {
                        hasError = true;
                        alert('Cada pregunta necesita al menos una respuesta.');
                        return;
                    }
                    var hasCorrect = answers.some(function(answer) { return answer.correct; });
                    if (!hasCorrect) {
                        hasError = true;
                        alert('Cada pregunta necesita al menos una respuesta marcada como correcta.');
                        return;
                    }
                    quizQuestions.push({
                        text: questionText,
                        answers: answers
                    });
                });
                if (hasError) {
                    return null;
                }
                if (!quizQuestions.length) {
                    return {minimum_correct: 0, questions: []};
                }
                var minimum = parseInt(minInput.value, 10);
                if (!minimum || minimum < 1) {
                    minimum = quizQuestions.length;
                }
                if (minimum > quizQuestions.length) {
                    minimum = quizQuestions.length;
                }
                return {
                    minimum_correct: minimum,
                    questions: quizQuestions
                };
            }

            function saveQuiz() {
                if (!activeContext.input) {
                    toggleModal(false);
                    return;
                }
                var data = collectQuizData();
                if (!data) {
                    return;
                }
                if (!data.questions.length) {
                    activeContext.input.value = '';
                } else {
                    activeContext.input.value = JSON.stringify(data);
                }
                updateSummary(activeContext.input, activeContext.summary, activeContext.trigger);
                toggleModal(false);
            }

            function clearQuiz() {
                if (!activeContext.input) {
                    toggleModal(false);
                    return;
                }
                activeContext.input.value = '';
                updateSummary(activeContext.input, activeContext.summary, activeContext.trigger);
                toggleModal(false);
            }

            if (addQuestionBtn) {
                addQuestionBtn.addEventListener('click', function() {
                    addQuestion();
                });
            }
            if (saveBtn) {
                saveBtn.addEventListener('click', saveQuiz);
            }
            if (clearBtn) {
                clearBtn.addEventListener('click', clearQuiz);
            }
            closeButtons.forEach(function(button) {
                button.addEventListener('click', function() {
                    toggleModal(false);
                });
            });
            quizBackdrop.addEventListener('click', function() {
                toggleModal(false);
            });

            triggers.forEach(function(trigger) {
                var targetSelector = trigger.getAttribute('data-quiz-target');
                var summarySelector = trigger.getAttribute('data-quiz-summary');
                var targetInput = targetSelector ? document.querySelector(targetSelector) : null;
                var summaryEl = summarySelector ? document.querySelector(summarySelector) : null;
                if (!targetInput) {
                    return;
                }
                trigger.addEventListener('click', function() {
                    activeContext.input = targetInput;
                    activeContext.summary = summaryEl;
                    activeContext.trigger = trigger;
                    if (modalTitle) {
                        modalTitle.textContent = trigger.getAttribute('data-quiz-title') || 'Autoevaluación';
                    }
                    loadQuestionsIntoModal(parseQuizValue(targetInput.value));
                    toggleModal(true);
                });
                updateSummary(targetInput, summaryEl, trigger);
            });
        });
