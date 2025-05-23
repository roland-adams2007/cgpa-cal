let latestCGPA = null;
let difficultySubjects = '';
let manualCGPA = null;

function addSubject() {
    const subjectsDiv = document.getElementById('gpaSubjects');
    const subjectCount = subjectsDiv.children.length + 1;
    const subjectDiv = document.createElement('div');
    subjectDiv.className = 'gpa-subject';

    let subjectHeading;
    switch (subjectCount) {
        case 1: subjectHeading = '1st Subject'; break;
        case 2: subjectHeading = '2nd Subject'; break;
        case 3: subjectHeading = '3rd Subject'; break;
        default: subjectHeading = `${subjectCount}th Subject`;
    }

    subjectDiv.innerHTML = `
        <h2>${subjectHeading}</h2>
        <input type="text" placeholder="Enter Subject Name" name="subject[]" required>
        <div class="gpa-grade-credit">
            <input type="number" step="0.01" placeholder="Enter Grade in Points" name="grade[]" required>
            <input type="number" step="0.01" placeholder="Enter Credit Hours" name="credit[]" required>
        </div>
    `;
    subjectsDiv.appendChild(subjectDiv);
}

function deleteSubject() {
    const subjectsDiv = document.getElementById('gpaSubjects');
    if (subjectsDiv.children.length > 1) {
        subjectsDiv.removeChild(subjectsDiv.lastChild);
    }
}

document.getElementById('gpaForm').addEventListener('submit', function(event) {
    event.preventDefault();
    const formData = new FormData(this);

    const xhr = new XMLHttpRequest();
    xhr.open('POST', 'calculate_gpa.php', true);
    xhr.onload = function() {
        if (xhr.status === 200) {
            document.getElementById('gpaResult').innerText = `Your GPA is: ${xhr.responseText}`;
        }
    };
    xhr.send(formData);
});

function addSemester() {
    const semestersDiv = document.getElementById('semesters');
    const semesterCount = semestersDiv.children.length + 1;
    const semesterDiv = document.createElement('div');
    semesterDiv.className = 'semester';

    let semesterHeading;
    switch (semesterCount) {
        case 1: semesterHeading = '1st Semester'; break;
        case 2: semesterHeading = '2nd Semester'; break;
        case 3: semesterHeading = '3rd Semester'; break;
        default: semesterHeading = `${semesterCount}th Semester`;
    }

    semesterDiv.innerHTML = `<h2>${semesterHeading}</h2><input type="number" step="0.01" required placeholder="Enter GPA" name="gpa[]" class="gpa">`;
    semestersDiv.appendChild(semesterDiv);
}

function deleteSemester() {
    const semestersDiv = document.getElementById('semesters');
    if (semestersDiv.children.length > 1) {
        semestersDiv.removeChild(semestersDiv.lastChild);
    }
}

document.getElementById('cgpaForm').addEventListener('submit', function(event) {
    event.preventDefault();
    const formData = new FormData(this);

    const xhr = new XMLHttpRequest();
    xhr.open('POST', 'assets/php/calculate_cgpa.php', true);
    xhr.onload = function() {
        if (xhr.status === 200) {
            latestCGPA = parseFloat(xhr.responseText);
            document.getElementById('result').innerText = `Your CGPA is: ${latestCGPA}`;
            document.getElementById('aiQuestionSection').style.display = 'block';
            const difficultyField = document.getElementById('difficultyField');
            difficultyField.style.display = latestCGPA < 3.0 ? 'block' : 'none';
            const manualCGPAField = document.getElementById('manualCGPAField');
            manualCGPAField.style.display = 'none';
            manualCGPA = null;
        }
    };
    xhr.send(formData);
});

document.getElementById('percentage_form').addEventListener('submit', function(e) {
    e.preventDefault();
    var percentage = document.getElementById('percentage_input_new').value;
    var formula = document.getElementById('percentage_formula_new').value;

    if (percentage === '') {
        alert('Please enter your percentage');
        return;
    }

    var xhr = new XMLHttpRequest();
    xhr.open('POST', 'calculate_percentage.php', true);
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    xhr.onreadystatechange = function () {
        if (xhr.readyState === 4 && xhr.status === 200) {
            document.getElementById('percentage_result_new').innerText = xhr.responseText;
        }
    };
    xhr.send('percentage=' + percentage + '&formula=' + formula);
});

document.getElementById('aiQuestionInput').addEventListener('input', function() {
    const question = this.value.trim().toLowerCase();
    const manualCGPAField = document.getElementById('manualCGPAField');
    if (question === 'how can i increase my cgpa?' && !latestCGPA) {
        manualCGPAField.style.display = 'block';
    } else if (manualCGPAField.style.display === 'block' && question !== 'how can i increase my cgpa?') {
        manualCGPAField.style.display = 'none';
        manualCGPA = null;
        document.getElementById('manualCGPAInput').value = '';
    }
});

document.getElementById('aiQuestionForm').addEventListener('submit', async function(event) {
    event.preventDefault();
    const question = document.getElementById('aiQuestionInput').value;
    difficultySubjects = document.getElementById('difficultyInput')?.value || '';
    const manualCGPAInput = document.getElementById('manualCGPAInput')?.value;
    if (!question) {
        alert('Please enter a question');
        return;
    }

    if (manualCGPAInput && !isNaN(parseFloat(manualCGPAInput))) {
        manualCGPA = parseFloat(manualCGPAInput);
    }

    const loader = document.getElementById('aiLoader');
    const submitButton = document.querySelector('#aiQuestionForm button[type="submit"]');
    loader.style.display = 'block';
    submitButton.disabled = true;

    const context = `You are an academic advisor helping computer engineering students. 
Provide specific, actionable advice in under 150 words. 
Current CGPA: ${manualCGPA || latestCGPA}. 
${difficultySubjects ? `Subjects student finds difficult: ${difficultySubjects}.` : ''}
Highlight actionable suggestions by wrapping them in <strong class="highlight">...</strong>.`;

    try {
        const response = await fetch('ai-suggestion.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `question=${encodeURIComponent(question)}&context=${encodeURIComponent(context)}`
        });

        const result = await response.json();
        const aiResultDiv = document.getElementById('aiResult');

        if (result.text) {
            let resourcesHtml = '';
            if (result.resources && result.resources.length > 0) {
                resourcesHtml += `
                    <div class="resources mt-4">
                        <h4><i class="fas fa-file-download mr-2"></i> Downloadable Resources</h4>
                        <ul class="list-group">
                            ${result.resources.map(resource => `
                                <li class="list-group-item">
                                    <i class="fas fa-file-pdf mr-2"></i>
                                    <a href="${resource.file}" download class="resource-link">${resource.description}</a>
                                </li>
                            `).join('')}
                        </ul>
                    </div>`;
            }

            let youtubeHtml = '';
            if (result.youtube_links && result.youtube_links.length > 0) {
                youtubeHtml += `
                    <div class="youtube-links mt-4">
                        <h4><i class="fab fa-youtube mr-2"></i> Recommended Videos</h4>
                        <ul class="list-group">
                            ${result.youtube_links.map(link => `
                                <li class="list-group-item">
                                    <i class="fas fa-video mr-2"></i>
                                    <a href="${link.url}" target="_blank" class="youtube-link">${link.title}</a>
                                </li>
                            `).join('')}
                        </ul>
                    </div>`;
            }

            aiResultDiv.innerHTML = `
                <div class="card ai-response-card">
                    <div class="card-header">
                        <h3><i class="fas fa-graduation-cap mr-2"></i> Academic Advisor Response</h3>
                    </div>
                    <div class="card-body">
                        <p class="response-text">${result.text}</p>
                        ${resourcesHtml}
                        ${youtubeHtml}
                        <div class="suggestions mt-4">
                            <h4><i class="fas fa-lightbulb mr-2"></i> Suggested Follow-up Questions</h4>
                            <ul class="list-group">
                                <li class="list-group-item"><i class="fas fa-chevron-right mr-2"></i> What specific resources would help me improve in ${difficultySubjects}?</li>
                                <li class="list-group-item"><i class="fas fa-chevron-right mr-2"></i> How should I balance my course load next semester?</li>
                                <li class="list-group-item"><i class="fas fa-chevron-right mr-2"></i> What study techniques work best for ${difficultySubjects}?</li>
                            </ul>
                        </div>
                    </div>
                </div>`;
        } else if (result.error) {
            aiResultDiv.innerHTML = `
                <div class="alert alert-danger" role="alert">
                    <i class="fas fa-exclamation-circle mr-2"></i>
                    <strong>Error:</strong> ${result.error}
                </div>`;
        } else {
            aiResultDiv.innerHTML = `
                <div class="alert alert-warning" role="alert">
                    <i class="fas fa-exclamation-triangle mr-2"></i>
                    Unexpected response format. Please try again later.
                </div>`;
        }
    } catch (error) {
        document.getElementById('aiResult').innerHTML = `
            <div class="alert alert-danger" role="alert">
                <i class="fas fa-exclamation-circle mr-2"></i>
                <strong>Error:</strong> Failed to fetch response. Please try again.
            </div>`;
    } finally {
        loader.style.display = 'none';
        submitButton.disabled = false;
    }
});

document.getElementById('clearButton')?.addEventListener('click', function() {
    document.getElementById('aiQuestionInput').value = '';
    document.getElementById('difficultyInput').value = '';
    document.getElementById('manualCGPAInput').value = '';
    document.getElementById('manualCGPAField').style.display = 'none';
    manualCGPA = null;
    document.getElementById('aiResult').innerHTML = '';
});

document.getElementById('newChatButton')?.addEventListener('click', function() {
    document.getElementById('aiQuestionInput').value = '';
    document.getElementById('aiResult').innerHTML = '';
});