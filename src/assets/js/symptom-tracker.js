/**
 * Symptom Tracker JavaScript
 * Handles dynamic symptom tracking interface with real-time updates
 */

class SymptomTracker {
    constructor(config) {
        this.csrfToken = config.csrfToken;
        this.existingSymptoms = config.existingSymptoms || {};
        this.baselineSymptoms = config.baselineSymptoms || {};
        
        this.init();
    }

    init() {
        this.setupEventListeners();
        this.updateAllSummaries();
        this.updateFormSummary();
    }

    setupEventListeners() {
        // Severity slider changes
        document.querySelectorAll('.severity-slider').forEach(slider => {
            slider.addEventListener('input', (e) => this.handleSeverityChange(e));
            slider.addEventListener('change', (e) => this.handleSeverityChange(e));
        });

        // Form submission
        document.getElementById('symptomForm').addEventListener('submit', (e) => {
            e.preventDefault();
            this.submitSymptoms();
        });

        // Initialize existing symptoms display
        this.initializeExistingSymptoms();
    }

    handleSeverityChange(event) {
        const slider = event.target;
        const severity = parseInt(slider.value);
        const category = slider.dataset.category;
        const symptom = slider.dataset.symptom;
        
        // Update severity display
        const severityValue = document.getElementById(`severity-${category}-${symptom}`);
        if (severityValue) {
            severityValue.textContent = severity;
            severityValue.className = `severity-value severity-${severity}`;
        }

        // Show/hide details section
        const detailsSection = document.getElementById(`details-${category}-${symptom}`);
        const symptomItem = slider.closest('.symptom-item');
        
        if (severity > 0) {
            detailsSection.style.display = 'block';
            symptomItem.classList.add('active');
        } else {
            detailsSection.style.display = 'none';
            symptomItem.classList.remove('active');
            this.clearSymptomDetails(category, symptom);
        }

        // Update slider background color based on severity
        this.updateSliderAppearance(slider, severity);
        
        // Update category summary
        this.updateCategorySummary(category);
        
        // Update form summary
        this.updateFormSummary();
    }

    updateSliderAppearance(slider, severity) {
        // Remove existing classes
        slider.className = slider.className.replace(/\bseverity-\d+\b/g, '');
        
        // Add new severity class
        slider.classList.add(`severity-${severity}`);
        
        // Update slider track color
        const percentage = (severity / 10) * 100;
        let color;
        
        if (severity === 0) {
            color = '#DEE2E6';
        } else if (severity <= 2) {
            color = '#90EE90';
        } else if (severity <= 4) {
            color = '#FFE4B5';
        } else if (severity <= 6) {
            color = '#FFC107';
        } else if (severity <= 8) {
            color = '#FF6B35';
        } else {
            color = '#DC3545';
        }
        
        slider.style.background = `linear-gradient(90deg, ${color} ${percentage}%, #DEE2E6 ${percentage}%)`;
    }

    clearSymptomDetails(category, symptom) {
        // Clear detail inputs when severity is set to 0
        const detailsSection = document.getElementById(`details-${category}-${symptom}`);
        if (detailsSection) {
            const inputs = detailsSection.querySelectorAll('input, textarea');
            inputs.forEach(input => {
                input.value = '';
            });
        }
    }

    updateCategorySummary(category) {
        const categorySliders = document.querySelectorAll(`.severity-slider[data-category="${category}"]`);
        let activeSymptoms = 0;
        let totalSeverity = 0;

        categorySliders.forEach(slider => {
            const severity = parseInt(slider.value);
            if (severity > 0) {
                activeSymptoms++;
                totalSeverity += severity;
            }
        });

        const avgSeverity = activeSymptoms > 0 ? (totalSeverity / activeSymptoms).toFixed(1) : 0;

        const summaryElement = document.getElementById(`summary-${category}`);
        if (summaryElement) {
            const symptomsCount = summaryElement.querySelector('.symptoms-count');
            const avgSeverityElement = summaryElement.querySelector('.avg-severity');

            if (symptomsCount) {
                symptomsCount.textContent = `${activeSymptoms} symptom${activeSymptoms !== 1 ? 's' : ''}`;
            }

            if (avgSeverityElement) {
                avgSeverityElement.textContent = `Avg: ${avgSeverity}`;
            }
        }
    }

    updateAllSummaries() {
        const categories = ['digestive', 'systemic', 'skin', 'mood', 'sleep', 'energy'];
        categories.forEach(category => {
            this.updateCategorySummary(category);
        });
    }

    updateFormSummary() {
        const allSliders = document.querySelectorAll('.severity-slider');
        let totalSymptoms = 0;
        let totalSeverity = 0;

        allSliders.forEach(slider => {
            const severity = parseInt(slider.value);
            if (severity > 0) {
                totalSymptoms++;
                totalSeverity += severity;
            }
        });

        const avgSeverity = totalSymptoms > 0 ? (totalSeverity / totalSymptoms).toFixed(1) : 0;

        const totalSymptomsElement = document.getElementById('totalSymptoms');
        const avgSeverityElement = document.getElementById('avgSeverity');

        if (totalSymptomsElement) {
            totalSymptomsElement.textContent = totalSymptoms;
        }

        if (avgSeverityElement) {
            avgSeverityElement.textContent = avgSeverity;
        }
    }

    initializeExistingSymptoms() {
        // Set existing symptom values and trigger updates
        Object.keys(this.existingSymptoms).forEach(category => {
            Object.keys(this.existingSymptoms[category]).forEach(symptom => {
                const symptomData = this.existingSymptoms[category][symptom];
                const slider = document.querySelector(`.severity-slider[data-category="${category}"][data-symptom="${symptom}"]`);
                
                if (slider && symptomData.severity) {
                    slider.value = symptomData.severity;
                    
                    // Trigger the change event to update UI
                    const event = new Event('input', { bubbles: true });
                    slider.dispatchEvent(event);
                }
            });
        });
    }

    async submitSymptoms() {
        const submitBtn = document.getElementById('submitBtn');
        const form = document.getElementById('symptomForm');
        
        // Show loading state
        submitBtn.classList.add('loading');
        submitBtn.disabled = true;
        form.classList.add('loading');

        try {
            const formData = new FormData(form);
            
            const response = await fetch(window.location.href, {
                method: 'POST',
                body: formData
            });

            if (!response.ok) {
                throw new Error('Network response was not ok');
            }

            const result = await response.json();

            if (result.success) {
                this.showSuccessModal(result.symptoms_logged);
                
                // Update form to show it's been submitted
                const alerts = document.querySelectorAll('.alert');
                alerts.forEach(alert => alert.remove());
                
                const successAlert = document.createElement('div');
                successAlert.className = 'alert alert-success';
                successAlert.innerHTML = '<strong>✅ Symptoms updated successfully!</strong> Your data has been saved.';
                
                document.querySelector('.container').insertBefore(
                    successAlert, 
                    document.querySelector('.symptom-form')
                );

                // Update button text
                submitBtn.textContent = 'Update Symptoms';
                
                // Add hidden input for future updates
                if (!form.querySelector('input[name="update_existing"]')) {
                    const updateInput = document.createElement('input');
                    updateInput.type = 'hidden';
                    updateInput.name = 'update_existing';
                    updateInput.value = '1';
                    form.appendChild(updateInput);
                }

            } else {
                throw new Error(result.message || 'Unknown error occurred');
            }

        } catch (error) {
            console.error('Error submitting symptoms:', error);
            
            // Show error alert
            const errorAlert = document.createElement('div');
            errorAlert.className = 'alert alert-error';
            errorAlert.innerHTML = `<strong>❌ Error:</strong> ${error.message || 'Failed to save symptoms. Please try again.'}`;
            
            document.querySelector('.container').insertBefore(
                errorAlert, 
                document.querySelector('.symptom-form')
            );

            // Remove error after 5 seconds
            setTimeout(() => {
                errorAlert.remove();
            }, 5000);

        } finally {
            // Remove loading state
            submitBtn.classList.remove('loading');
            submitBtn.disabled = false;
            form.classList.remove('loading');
        }
    }

    showSuccessModal(symptomsLogged) {
        const modal = document.getElementById('success-modal');
        const modalStats = document.getElementById('symptoms-logged');

        if (modalStats) {
            modalStats.textContent = symptomsLogged;
        }

        if (modal) {
            modal.classList.remove('hidden');

            // Auto-close after 3 seconds
            setTimeout(() => {
                this.closeModal();
            }, 3000);
        }
    }

    closeModal() {
        const modal = document.getElementById('success-modal');
        if (modal) {
            modal.classList.add('hidden');
        }
    }

    // Utility method to get current symptom data
    getCurrentSymptomData() {
        const data = {};
        const sliders = document.querySelectorAll('.severity-slider');
        
        sliders.forEach(slider => {
            const category = slider.dataset.category;
            const symptom = slider.dataset.symptom;
            const severity = parseInt(slider.value);
            
            if (severity > 0) {
                if (!data[category]) {
                    data[category] = {};
                }
                
                data[category][symptom] = {
                    severity: severity
                };
                
                // Get additional details if they exist
                const detailsSection = document.getElementById(`details-${category}-${symptom}`);
                if (detailsSection && detailsSection.style.display !== 'none') {
                    const duration = detailsSection.querySelector('.duration-input')?.value;
                    const triggers = detailsSection.querySelector('.triggers-input')?.value;
                    const notes = detailsSection.querySelector('.detail-textarea')?.value;
                    
                    if (duration) data[category][symptom].duration = duration;
                    if (triggers) data[category][symptom].triggers = triggers;
                    if (notes) data[category][symptom].notes = notes;
                }
            }
        });
        
        return data;
    }

    // Method to highlight baseline symptoms
    highlightBaselineSymptoms() {
        Object.keys(this.baselineSymptoms).forEach(category => {
            const symptoms = this.baselineSymptoms[category];
            if (Array.isArray(symptoms)) {
                symptoms.forEach(symptom => {
                    const symptomItem = document.querySelector(
                        `.symptom-item[data-category="${category}"][data-symptom="${symptom}"]`
                    );
                    if (symptomItem) {
                        symptomItem.classList.add('baseline-symptom');
                    }
                });
            }
        });
    }

    // Method to provide helpful tips based on symptoms
    showSymptomTips(category, symptom, severity) {
        const tips = {
            digestive: {
                bloating: [
                    "Try eating smaller, more frequent meals",
                    "Consider digestive enzymes with meals",
                    "Chew food thoroughly and eat slowly"
                ],
                constipation: [
                    "Increase water intake gradually",
                    "Add more fiber-rich AIP vegetables",
                    "Try gentle movement after meals"
                ]
            },
            systemic: {
                fatigue: [
                    "Ensure adequate sleep (7-9 hours)",
                    "Consider checking iron levels",
                    "Balance activity with rest periods"
                ],
                joint_pain: [
                    "Try gentle stretching or yoga",
                    "Apply heat or cold therapy",
                    "Consider anti-inflammatory foods"
                ]
            }
        };

        return tips[category]?.[symptom] || [];
    }
}

// Close modal when clicking outside
document.addEventListener('click', function(e) {
    const modal = document.getElementById('success-modal');
    if (e.target === modal) {
        if (window.symptomTracker) {
            window.symptomTracker.closeModal();
        }
    }
});

// Global function for modal close button
function closeModal() {
    const modal = document.getElementById('success-modal');
    if (modal) {
        modal.classList.add('hidden');
    }
}

// Make SymptomTracker globally available
window.SymptomTracker = SymptomTracker;