<?php
use AvalancheStudio\AvalancheCRM\Models\Project;


$notStarted = Project::where('status', 'pending')->count();
$inProgress = Project::where('status', 'in_progress')->count();
$onHold = Project::where('status', 'on_hold')->count();
$finished = Project::where('status', 'completed')->count();
$cancelled = Project::where('status', 'cancelled')->count();
?>

<div class="projects-summary-container" style="margin-bottom: 20px; padding: 0 20px;">
    <h3 style="margin-bottom: 15px; font-weight: 600; color: #3A4E5A; font-size: 1.1rem;">Projects Summary</h3>
    <div style="display: flex; gap: 15px; flex-wrap: wrap;">
        <!-- Not Started -->
        <a href="<?= \Backend::url('avalanchestudio/avalanchecrm/projects') ?>?status=pending"
            style="flex: 1; min-width: 140px; background: #fff; border: 1px solid #e0e0e0; border-radius: 4px; padding: 10px 15px; text-decoration: none; display: block;">
            <div style="font-size: 13px; color: #66727B; margin-bottom: 2px;">Not Started</div>
            <div style="font-size: 18px; font-weight: 600; color: #405ADF;">
                <?= $notStarted ?>
            </div>
        </a>
        <!-- In Progress -->
        <a href="<?= \Backend::url('avalanchestudio/avalanchecrm/projects') ?>?status=in_progress"
            style="flex: 1; min-width: 140px; background: #fff; border: 1px solid #e0e0e0; border-radius: 4px; padding: 10px 15px; text-decoration: none; display: block;">
            <div style="font-size: 13px; color: #405ADF; margin-bottom: 2px;">In Progress</div>
            <div style="font-size: 18px; font-weight: 600; color: #405ADF;">
                <?= $inProgress ?>
            </div>
        </a>
        <!-- On Hold -->
        <a href="<?= \Backend::url('avalanchestudio/avalanchecrm/projects') ?>?status=on_hold"
            style="flex: 1; min-width: 140px; background: #fff; border: 1px solid #e0e0e0; border-radius: 4px; padding: 10px 15px; text-decoration: none; display: block;">
            <div style="font-size: 13px; color: #EB6B3D; margin-bottom: 2px;">On Hold</div>
            <div style="font-size: 18px; font-weight: 600; color: #405ADF;">
                <?= $onHold ?>
            </div>
        </a>
        <!-- Cancelled -->
        <a href="<?= \Backend::url('avalanchestudio/avalanchecrm/projects') ?>?status=cancelled"
            style="flex: 1; min-width: 140px; background: #fff; border: 1px solid #e0e0e0; border-radius: 4px; padding: 10px 15px; text-decoration: none; display: block;">
            <div style="font-size: 13px; color: #9B9CA0; margin-bottom: 2px;">Cancelled</div>
            <div style="font-size: 18px; font-weight: 600; color: #405ADF;">
                <?= $cancelled ?>
            </div>
        </a>
        <!-- Finished -->
        <a href="<?= \Backend::url('avalanchestudio/avalanchecrm/projects') ?>?status=completed"
            style="flex: 1; min-width: 140px; background: #fff; border: 1px solid #e0e0e0; border-radius: 4px; padding: 10px 15px; text-decoration: none; display: block;">
            <div style="font-size: 13px; color: #28A745; margin-bottom: 2px;">Finished</div>
            <div style="font-size: 18px; font-weight: 600; color: #405ADF;">
                <?= $finished ?>
            </div>
        </a>
    </div>
</div>