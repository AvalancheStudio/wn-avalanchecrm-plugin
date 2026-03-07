<div class="ticket-management-nav-wrapper">
    <div class="ticket-management-nav">
        <div class="nav-container">
            <a href="<?= Backend::url('avalanchestudio/avalanchecrm/tickets') ?>" 
               class="nav-btn <?= $this instanceof \AvalancheStudio\AvalancheCRM\Controllers\Tickets ? 'btn-active' : 'btn-inactive' ?>">
                <i class="oc-icon-ticket"></i>
                <span>Tickets</span>
            </a>
            <a href="<?= Backend::url('avalanchestudio/avalanchecrm/ticketcategories') ?>" 
               class="nav-btn <?= $this instanceof \AvalancheStudio\AvalancheCRM\Controllers\TicketCategories ? 'btn-active' : 'btn-inactive' ?>">
                <i class="oc-icon-tags"></i>
                <span>Categories</span>
            </a>
            <a href="<?= Backend::url('avalanchestudio/avalanchecrm/ticketstatuses') ?>" 
               class="nav-btn <?= $this instanceof \AvalancheStudio\AvalancheCRM\Controllers\TicketStatuses ? 'btn-active' : 'btn-inactive' ?>">
                <i class="oc-icon-check-circle"></i>
                <span>Statuses</span>
            </a>
            <a href="<?= Backend::url('avalanchestudio/avalanchecrm/tickettypes') ?>" 
               class="nav-btn <?= $this instanceof \AvalancheStudio\AvalancheCRM\Controllers\TicketTypes ? 'btn-active' : 'btn-inactive' ?>">
                <i class="oc-icon-file-text-o"></i>
                <span>Ticket Types</span>
            </a>
        </div>
    </div>
</div>

<style>
    .ticket-management-nav-wrapper {
        margin: -20px 0 30px 0; /* Only top and bottom margin */
        background: #ffffff;
        border-bottom: 1px solid #e0e0e0;
        box-shadow: 0 2px 10px rgba(0,0,0,0.03);
        width: 100%;
    }
    
    .ticket-management-nav {
        padding: 20px;
        text-align: center;
    }
    
    .nav-container {
        display: inline-flex;
        flex-wrap: wrap;
        gap: 15px;
        justify-content: center;
        align-items: center;
        max-width: 100%;
        margin: 0 auto;
    }
    
    .nav-btn {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        min-width: 120px;
        padding: 12px 10px;
        border-radius: 8px;
        transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
        border: 1px solid transparent;
        cursor: pointer;
        text-decoration: none !important;
    }
    
    .nav-btn i {
        font-size: 24px;
        margin-bottom: 8px;
        display: block;
    }
    
    .nav-btn span {
        font-size: 13px;
        font-weight: 600;
        display: block;
    }
    
    .btn-active {
        background-color: #3498db !important;
        color: #ffffff !important;
        box-shadow: 0 4px 15px rgba(52, 152, 219, 0.3);
        border-bottom: 3px solid #2980b9;
    }
    
    .btn-active i, .btn-active span {
        color: #ffffff !important;
    }

    .btn-inactive {
        background-color: #f8f9fa !important;
        color: #555 !important;
        border: 1px solid #e9ecef !important;
    }
    
    .btn-inactive:hover {
        background-color: #ffffff !important;
        color: #3498db !important;
        border-color: #3498db !important;
        transform: translateY(-3px);
        box-shadow: 0 5px 15px rgba(0,0,0,0.08);
    }
    
    .btn-inactive:hover i {
        color: #3498db !important;
    }

    /* Small Screen Responsiveness */
    @media (max-width: 768px) {
        .nav-container {
            gap: 10px;
        }
        .nav-btn {
            min-width: 80px;
            padding: 8px 5px;
        }
        .nav-btn i {
            font-size: 18px;
            margin-bottom: 4px;
        }
        .nav-btn span {
            font-size: 11px;
        }
    }

    @media (max-width: 480px) {
        .nav-container {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            width: 100%;
        }
        .nav-btn {
            min-width: unset;
        }
    }
</style>